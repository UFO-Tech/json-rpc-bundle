<?php

namespace Ufo\JsonRpcBundle\ConfigService;

final readonly class RpcAsyncConfig
{
    const NAME = 'async';
    const RPC_ASYNC = 'rpc_async';
    const FAILED = 'failed';

    public ?string $rpcAsync;

    public ?string $failed;

    public function __construct(array $rpcConfigs, public RpcMainConfig $parent)
    {
        $this->rpcAsync = $rpcConfigs[self::RPC_ASYNC];
        $this->failed = $rpcConfigs[self::FAILED];
    }

}