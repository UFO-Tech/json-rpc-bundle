<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Events;

use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcRequest;

class RpcAsyncOutputEvent extends BaseRpcEvent
{
    public const string NAME = RpcEvent::OUTPUT_ASYNC;

    public function __construct(
        public RpcRequest $rpcRequest,
        public RpcBatchRequest $batchRequest,
        public string $output,
    ) {}

}