<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Events;

class RpcAsyncRequestEvent extends RpcRequestEvent
{
    public const string NAME = RpcEvent::REQUEST_ASYNC;

}