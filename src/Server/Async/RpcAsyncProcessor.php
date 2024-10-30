<?php

namespace Ufo\JsonRpcBundle\Server\Async;

use Closure;
use DateTime;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Ufo\JsonRpcBundle\CliCommand\UfoRpcProcessCommand;
use Ufo\JsonRpcBundle\Security\TokenRpcCliSecurity;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\JsonRpcBundle\Server\RpcServer;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcObject\RpcAsyncRequest;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Transformer\RpcResponseContextBuilder;

use function array_merge;
use function is_null;
use function sprintf;
use function time;

use const JSON_UNESCAPED_UNICODE;
use const PHP_EOL;

#[AsMessageHandler]
class RpcAsyncProcessor
{
    const R = 'rpc.refresh.queue';
    const CONSOLE = 'bin/console';

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
        protected TokenRpcCliSecurity $rpcSecurity,
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
        string $token = null,
        array $additionParams = [],
        string $cwd = null,
        array $env = null,
        mixed $input = null,
        ?float $timeout = 60
    ): Process {
        $console = ($_SERVER['SCRIPT_NAME']) === static::CONSOLE ? static::CONSOLE : '../'.static::CONSOLE;
        if (empty($this->processes)) {
            $this->processes[static::R] = static::R;
            $this->counter[static::R] = 0;
        }
        $start = [
            $console,
            UfoRpcProcessCommand::COMMAND_NAME,
            // todo regenerate raw json
            $request->getRawJson(),
        ];
        if (!empty($token)) {
            $start[] = '-t'.$token;
        }
        $process = new Process(array_merge($start, $additionParams), $cwd, $env, $input, $timeout);
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

        $this->rpcSecurity->setToken($message->token);
        $this->rpcSecurity->isValidRequest();

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
