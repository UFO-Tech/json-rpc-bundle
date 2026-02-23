<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Events;

use Throwable;
use Ufo\RpcObject\RpcError;
use Ufo\RpcObject\RpcRequest;

class RpcErrorEvent extends BaseRpcEvent
{
    public const string NAME = RpcEvent::ERROR;

    public RpcError $rpcError;

    public function __construct(
        public RpcRequest $rpcRequest,
        public Throwable $exception,
    ) {
        $this->rpcError = RpcError::fromThrowable($this->exception);
    }

}