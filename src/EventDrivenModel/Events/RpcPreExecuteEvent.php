<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Events;

use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RpcRequest;

class RpcPreExecuteEvent extends BaseRpcEvent
{
    public const string NAME = RpcEvent::PRE_EXECUTE;

    public function __construct(
        public RpcRequest $rpcRequest,
        public Service $service,
        public array $params = [],
    ) {}

}