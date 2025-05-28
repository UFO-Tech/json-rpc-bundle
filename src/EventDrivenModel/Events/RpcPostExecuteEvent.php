<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Events;

use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RpcRequest;

class RpcPostExecuteEvent extends BaseRpcEvent
{
    public const string NAME = RpcEvent::POST_EXECUTE;

    public function __construct(
        public mixed $result,
        public RpcRequest $rpcRequest,
        public Service $service,
        public array $params = [],
    ) {}

}