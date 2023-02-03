<?php

namespace Ufo\JsonRpcBundle\Server\Async;

use Symfony\Component\Process\Process;
use Ufo\JsonRpcBundle\CliCommand\UfoRpcProcessCommand;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcObject\RpcRequest;

class RpcAsyncProcessor
{
    const R = 'rpc.refresh.queue';
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
     * @return Process[]
     */
    public function &getProcesses(): array
    {
        return $this->processes;
    }

    public function createProcesses(
        RpcRequest $request,
        string     $token = null,
        array      $additionParams = [],
        string     $cwd = null,
        array      $env = null,
        mixed      $input = null,
        ?float     $timeout = 60
    ): Process
    {
        if (empty($this->processes)) {
            $this->processes[static::R] = static::R;
            $this->counter[static::R] = 0;
        }

        $start = [
            '../bin/console',
            UfoRpcProcessCommand::COMMAND_NAME,
            // todo regenerate raw json
            $request->getRawJson(),
        ];
        if (!is_null($token)) {
            $start[] = '-t' . $token;
        }

        $process = new Process(
            array_merge($start, $additionParams),
            $cwd,
            $env,
            $input,
            $timeout
        );
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

    protected function refreshProcessInPull(string|int $id)
    {
        $process = $this->processes[$id];
        $this->removeProcessFromPull($id);
        $this->processes[$id] = $process;
    }

    protected function removeProcessFromPull(string|int $id)
    {
        unset($this->processes[$id]);
    }

    /**
     * @param \Closure|null $callback function(string $output) {}
     * @return array
     * @throws RpcAsyncRequestException
     */
    public function process(?\Closure $callback = null): array
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
                        $this->requestObjects[$id]->setError(
                            new RpcAsyncRequestException('Asynchronous request does not respond')
                        );
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

}
