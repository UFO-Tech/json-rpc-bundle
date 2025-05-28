<?php

namespace Ufo\JsonRpcBundle\ConfigService;

final readonly class RpcAsyncConfig
{
    const string NAME = 'async';
    const string RPC_ASYNC = 'rpc_async';
    const string FAILED = 'failed';

    public ?string $rpcAsync;

    public ?string $failed;

    public function __construct(array $rpcConfigs, public RpcMainConfig $parent)
    {
        $this->rpcAsync = $rpcConfigs[self::RPC_ASYNC] ?? null;
        $this->failed = $rpcConfigs[self::FAILED] ?? null;
    }

}