<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Events;

use Ufo\RpcObject\RpcRequest;

class RpcRequestEvent extends BaseRpcEvent
{
    public const string NAME = RpcEvent::REQUEST;

    public function __construct(
        public RpcRequest $rpcRequest
    ) {}

}