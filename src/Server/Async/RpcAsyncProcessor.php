<?php

namespace Ufo\JsonRpcBundle\Server\Async;

use Closure;
use DateTime;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Ufo\JsonRpcBundle\CliCommand\UfoRpcProcessCommand;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Security\TokenHolders\RpcAsyncTokenHolder;
use Ufo\JsonRpcBundle\Server\RpcServer;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcObject\RpcAsyncRequest;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use function is_null;
use function sprintf;
use function time;

use const JSON_UNESCAPED_UNICODE;
use const PHP_EOL;

#[AsMessageHandler]
class RpcAsyncProcessor
{
    const string R = 'rpc.refresh.queue';
    const string CONSOLE = 'bin/console';

    /**
     * @var Process[]
     */
    protected array $processes = [];

    protected array $counter = [];

    /**
     * @var RpcRequest[]
     */
    protected array $requestObjects = [];

    public function __construct(
        protected RpcServer $rpcServer,
        protected SerializerInterface $serializer,
        protected IRpcSecurity $rpcSecurity,
        protected RpcCallbackProcessor $callbackProcessor,
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
    ): Process {
        $console = ($_SERVER['SCRIPT_NAME']) === static::CONSOLE ? static::CONSOLE : '../'.static::CONSOLE;
        $start = [
            $console,
            UfoRpcProcessCommand::COMMAND_NAME,
            (string)$request->getRawJson(),
        ];
        if (!empty($tokenName) && !empty($token)) {
            $start[] = '--' . $tokenName . '=' . $token;
        }
        $process = new Process([...$start, ...$additionParams], $cwd, $env, $input, $timeout);
        $process->start();
        $this->processes[$request->getId()] = $process;
        $this->counter[$request->getId()] = 0;
        $this->requestObjects[$request->getId()] = $request;

        return $process;
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
        foreach ($queue as $id => &$process) {
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
                    continue;
                }
            }
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
        echo PHP_EOL;
        echo (new DateTime())->format('Y-m-d H:i:s') . ':';
        echo PHP_EOL;
        echo '>>> ' . $this->serializer->serialize(
                $message->getRpcRequest()->toArray(),
                'json',
                ['json_encode_options' => JSON_UNESCAPED_UNICODE]
            );
        echo PHP_EOL;

        $this->rpcSecurity->setTokenHolder(new RpcAsyncTokenHolder($message));
        $this->rpcSecurity->isValidApiRequest();

        $response = $this->rpcServer->handle($message->getRpcRequest());
        try {
            if ($message->getRpcRequest()->isAsync()) {
                $this->callbackProcessor->process($message->getRpcRequest());
            }
            $group = $message->getRpcRequest()->hasError() ? RpcResponse::IS_ERROR : RpcResponse::IS_RESULT;
        } catch (Throwable $e) {
            $group = RpcResponse::IS_ERROR;
        }
        echo '<<< ' . $this->serializer->serialize(
                $response,
                'json',
                [
                    'groups' => [$group],
                    'json_encode_options' => JSON_UNESCAPED_UNICODE
                ]
            );
        echo PHP_EOL.PHP_EOL;
    }

}
