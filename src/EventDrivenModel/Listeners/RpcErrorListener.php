<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcErrorEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\RpcError\ConstraintsImposedException;
use Ufo\RpcObject\RpcError;

#[AsEventListener(RpcEvent::ERROR, method: 'onConstraintsImpostError', priority: 1000)]
class RpcErrorListener
{
    public function __construct() {}

    public function onConstraintsImpostError(RpcErrorEvent $event): void
    {
        $e = $event->exception;
        if (!$e instanceof ConstraintsImposedException) {
            return;
        }
        $event->rpcError = new RpcError($e->getCode(), $e->getMessage(), $e->getConstraintsImposed());
    }


}
