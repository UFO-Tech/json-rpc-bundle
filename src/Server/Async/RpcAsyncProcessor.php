<?php

namespace Ufo\JsonRpcBundle\Server\Async;

use Closure;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;
use Ufo\RpcObject\RPC\Info;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Ufo\JsonRpcBundle\CliCommand\UfoRpcProcessCommand;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Security\TokenHolders\RpcAsyncTokenHolder;
use Ufo\JsonRpcBundle\Server\RpcServer;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\RpcObject\RPC\APIGroups;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcError\RpcTokenNotSentException;
use Ufo\RpcObject\RpcAsyncRequest;
use Ufo\RpcObject\RpcNotificationRequest;
use Ufo\RpcObject\RpcRequest;
use function count;
use function is_null;
use function sprintf;
use function time;
use function usleep;


#[AsMessageHandler]
class RpcAsyncProcessor
{
    const string R = 'rpc.refresh.queue';
    const string CONSOLE = 'bin/console';
    /**
     * Pause between polling rounds when no process has finished, so waiting does not spin the CPU.
     */
    const int POLL_INTERVAL_US = 2000;
    /**
     * How long the worker waits for a detached process to take a request over before running it itself.
     */
    const float DETACH_HANDOVER_TIMEOUT = 3.0;

    /**
     * @var Process[]
     */
    protected array $processes = [];

    protected array $counter = [];

    /**
     * @var RpcRequest[]
     */
    protected array $requestObjects = [];

    /**
     * Ids of created processes that wait for a free slot (batch.process_count).
     *
     * @var array<string|int, true>
     */
    protected array $pending = [];

    public function __construct(
        protected RpcServer $rpcServer,
        protected SerializerInterface $serializer,
        protected IRpcSecurity $rpcSecurity,
        protected RpcCallbackProcessor $callbackProcessor,
        protected ApiGroupContext $apiGroupContext,
        protected RpcMainConfig $rpcConfig,
        #[Autowire('%kernel.project_dir%')]
        protected ?string $projectDir = null,
        protected ?RpcDetachedSlots $detachedSlots = null,
        protected ?LoggerInterface $logger = null,
    ) {}

    /**
     * @return Process[]
     */
    public function &getProcesses(): array
    {
        return $this->processes;
    }

    public function createProcesses(
        RpcRequest $request,
        ?string $tokenName = null,
        ?string $token = null,
        array $additionParams = [],
        ?string $cwd = null,
        ?array $env = null,
        mixed $input = null,
        ?float $timeout = 3600
    ): Process
    {
        $start = [
            ...$this->consoleCommand(),
            UfoRpcProcessCommand::COMMAND_NAME,
            // The request goes through stdin and the token through env, so neither is visible in the process list.
            $input === null ? UfoRpcProcessCommand::JSON_FROM_STDIN : (string)$request->getRawJson(),
            '--api-group='.($this->apiGroupContext->getGroup() ?? APIGroups::DEFAULT_GROUP),
            '--ver='.($request->apiVersion ?? Info::DEFAULT_VERSION),
        ];
        if (!empty($tokenName) && !empty($token)) {
            $env = [...($env ?? []), UfoRpcProcessCommand::TOKEN_ENV => $token];
        }
        $process = new Process([...$start, ...$additionParams], $cwd, $env, $input ?? (string)$request->getRawJson(), $timeout);
        if ($this->runningCount() < $this->maxParallel()) {
            $process->start();
        } else {
            $this->pending[$request->getId()] = true;
        }
        $this->processes[$request->getId()] = $process;
        $this->counter[$request->getId()] = 0;
        $this->requestObjects[$request->getId()] = $request;

        return $process;
    }

    /**
     * Starts `ufo:rpc:process` for the request fully detached from the current (FPM) process:
     * the shell starts the command in the background and exits, so this process waits only for the shell
     * (no zombie is left) and the command is adopted by the init process. The request goes through env
     * (the stdin of a background command is /dev/null), the token too, so neither is in the process list.
     * Returns once the detached process has taken the request over (after its kernel boot), see
     * RpcDetachedSlots::createTicket(): the request is executed exactly once, there or here.
     *
     * @return bool false: it was not taken over (no free slot, proc_open unavailable, the process did not
     *              start in time, ...) and must be executed by the caller
     */
    public function detach(RpcRequest $request): bool
    {
        $slot = $this->detachedSlots?->free($this->rpcConfig->notificationConfig->processCount);
        if ($slot === null) {
            return false;
        }
        $ticket = $this->detachedSlots->createTicket();
        try {
            $env = [UfoRpcProcessCommand::REQUEST_ENV => (string)$request->getRawJson()];
            try {
                $env[UfoRpcProcessCommand::TOKEN_ENV] = $this->rpcSecurity->getTokenHolder()->getToken();
            } catch (RpcTokenNotSentException) {}

            $shell = new Process([
                'sh', '-c', '"$@" >/dev/null 2>&1 &', 'sh',
                ...$this->consoleCommand(),
                UfoRpcProcessCommand::COMMAND_NAME,
                UfoRpcProcessCommand::JSON_FROM_ENV,
                '--api-group='.$this->apiGroupContext->getGroup(),
                '--ver='.($request->apiVersion ?? Info::DEFAULT_VERSION),
                '--'.UfoRpcProcessCommand::DETACHED_SLOT.'='.$slot,
                '--'.UfoRpcProcessCommand::DETACHED_TICKET.'='.$ticket,
            ], env: $env, timeout: 10);
            $shell->run();
            if ($shell->isSuccessful()) {
                return $this->detachedSlots->awaitClaim($ticket, static::DETACH_HANDOVER_TIMEOUT);
            }
        } catch (Throwable) {}

        // Not started: take the ticket back, so a process that starts late does not execute it too.
        return !$this->detachedSlots->claim($ticket);
    }

    /**
     * notification.process for a notification inside a batch: detached like a single one, so the batch
     * response does not wait for it. false: execute it the way the batch mode does.
     */
    public function detachBatchNotification(RpcRequest $request): bool
    {
        if (!$request instanceof RpcNotificationRequest || !$this->rpcConfig->notificationConfig->process) {
            return false;
        }
        // @FROM params may have been substituted since the request was parsed.
        $request->refreshRawJson($this->serializer);
        return $this->detach($request);
    }

    /**
     * @return string[] how to run bin/console of the application
     */
    protected function consoleCommand(): array
    {
        if ($this->projectDir) {
            return [(new PhpExecutableFinder())->find() ?: 'php', $this->projectDir.'/bin/console'];
        }
        return [($_SERVER['SCRIPT_NAME'] ?? '') === static::CONSOLE ? static::CONSOLE : '../'.static::CONSOLE];
    }

    /**
     * @throws RpcAsyncRequestException
     */
    public function getProcessById(string|int $id): ?Process
    {
        if (!isset($this->processes[$id])) {
            throw new RpcAsyncRequestException(sprintf('Process %s not found', $id));
        }

        return $this->processes[$id];
    }

    /**
     * Started processes that are not collected yet (a finished one counts until its output is taken).
     */
    protected function runningCount(): int
    {
        return count($this->processes) - count($this->pending) - (isset($this->processes[static::R]) ? 1 : 0);
    }

    /**
     * batch.process_count.
     */
    protected function maxParallel(): int
    {
        return $this->rpcConfig->batchConfig->processCount;
    }

    protected function refreshProcessInPull(string|int $id): void
    {
        $process = $this->processes[$id];
        $this->removeProcessFromPull($id);
        $this->processes[$id] = $process;
    }

    protected function removeProcessFromPull(string|int $id): void
    {
        unset($this->processes[$id]);
    }

    /**
     * @param Closure|null $callback function(string $output) {}
     * @return array
     * @throws RpcAsyncRequestException
     */
    public function process(?Closure $callback = null): array
    {
        $results = [];
        $queue = &$this->getProcesses();
        $idle = 0;
        foreach ($queue as $id => &$process) {
            if (isset($this->pending[$id])) {
                if ($this->runningCount() < $this->maxParallel()) {
                    $process->start();
                    unset($this->pending[$id]);
                    $idle = 0;
                } elseif (++$idle >= count($queue)) {
                    usleep(static::POLL_INTERVAL_US);
                    $idle = 0;
                }
                $this->refreshProcessInPull($id);
                continue;
            }
            if ($id === static::R || $this->getProcessById($id)->isRunning()) {
                $this->counter[$id]++;
                $needRefresh = true;
                if ($id !== static::R) {
                    if (time() >= $process->getStartTime() + $process->getTimeout()) {
                        $needRefresh = false;
                        $process->stop(0);
                        $this->requestObjects[$id]->setError(new RpcAsyncRequestException('Asynchronous request does not respond'));
                    }
                }
                if ($needRefresh) {
                    $this->refreshProcessInPull($id);
                    // A full round over the queue without any finished process — wait before the next one.
                    if (++$idle >= count($queue)) {
                        usleep(static::POLL_INTERVAL_US);
                        $idle = 0;
                    }
                    continue;
                }
            }
            $idle = 0;
            $results[$id] = $process->getOutput();
            if (!is_null($callback)) {
                $callback($results[$id], $this->requestObjects[$id]);
            }
            $this->removeProcessFromPull($id);
        }

        return $results;
    }

    public function __invoke(RpcAsyncRequest $message): void
    {
        $this->apiGroupContext->setGroup($message->apiGroup ?? APIGroups::DEFAULT_GROUP);
        try {
            $this->rpcSecurity->setTokenHolder(new RpcAsyncTokenHolder($message));
            $this->rpcSecurity->isValidApiRequest();
            $this->processAsync($message->rpcRequest);
        } finally {
            $this->apiGroupContext->reset();
        }
    }

    /**
     * Executes a notification or a request with $rpc.callback after the response.
     * Logs instead of printing: in an HTTP worker the output would go to a client that is gone
     * (without fastcgi_finish_request() PHP then aborts the script before the method runs),
     * and in a child process it would be mixed into the output its parent reads.
     */
    public function processAsync(RpcRequest $request): void
    {
        $response = $this->rpcServer->handle($request);
        $callbackError = null;
        try {
            if ($request->isAsync()) {
                $this->callbackProcessor->process($request);
            }
        } catch (Throwable $e) {
            $callbackError = $e->getMessage();
        }
        $this->logger?->info('RPC request executed after the response', [
            'method' => $request->getMethod(),
            'params' => $request->getParams(),
            'result' => $request->hasError() ? null : $response->getResult(),
            'error' => $request->hasError() ? $request->getError()->getMessage() : null,
            'callback_error' => $callbackError,
        ]);
    }

}
