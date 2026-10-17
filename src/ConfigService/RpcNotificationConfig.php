<?php

namespace Ufo\JsonRpcBundle\ConfigService;

/**
 * What runs after the response of a single HTTP request: notifications and requests with $rpc.callback.
 */
final readonly class RpcNotificationConfig
{
    const string NAME = 'notification';
    const string PROCESS = 'process';
    const string PROCESS_COUNT = 'process_count';
    const int DEFAULT_PROCESS_COUNT = 2;
    const int MAX_PROCESS_COUNT = 10;

    /**
     * false: executed inside the HTTP worker after the response (kernel.terminate);
     * true: executed by a `ufo:rpc:process` started detached from the worker, so the worker is free at once.
     */
    public bool $process;

    /**
     * Process mode only: how many detached processes may run at the same time; beyond that it runs inline.
     */
    public int $processCount;

    public function __construct(array $rpcConfigs, public RpcMainConfig $parent)
    {
        $this->process = $rpcConfigs[self::PROCESS] ?? false;
        $this->processCount = $rpcConfigs[self::PROCESS_COUNT] ?? self::DEFAULT_PROCESS_COUNT;
    }
}
