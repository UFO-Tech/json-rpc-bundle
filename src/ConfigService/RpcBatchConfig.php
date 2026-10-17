<?php

namespace Ufo\JsonRpcBundle\ConfigService;

final readonly class RpcBatchConfig
{
    const string NAME = 'batch';
    const string ENABLED = 'enabled';
    const string PROCESS = 'process';
    const string PROCESS_COUNT = 'process_count';
    const int DEFAULT_PROCESS_COUNT = 2;
    const int MAX_PROCESS_COUNT = 10;

    /**
     * false: batch requests are rejected with BatchIsDisabledException.
     */
    public bool $enabled;

    /**
     * true: every batch item runs in its own `ufo:rpc:process` child process (in parallel);
     * false: items run one by one inside the current process.
     */
    public bool $process;

    /**
     * Process mode only: how many child processes may run at the same time, the rest wait in a queue.
     */
    public int $processCount;

    public function __construct(array $rpcConfigs, public RpcMainConfig $parent)
    {
        $this->enabled = $rpcConfigs[self::ENABLED] ?? true;
        $this->process = $rpcConfigs[self::PROCESS] ?? true;
        $this->processCount = (int)($rpcConfigs[self::PROCESS_COUNT] ?? self::DEFAULT_PROCESS_COUNT);
    }
}
