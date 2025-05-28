<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Events;

use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

class RpcPostResponseEvent extends BaseRpcEvent
{
    public const string NAME = RpcEvent::POST_RESPONSE;

    public function __construct(
        public RpcResponse $response,
        public RpcRequest $rpcRequest,
        public Service $service,
    ) {}

}