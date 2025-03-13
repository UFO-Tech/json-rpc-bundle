<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Throwable;
use Ufo\RpcObject\Events\RpcErrorEvent;
use Ufo\RpcObject\Events\RpcEvent;

#[AsEventListener(RpcEvent::ERROR, 'onError')]
class LoggerListener
{

    public function __construct(
        protected LoggerInterface $logger
    ) {}

    public function onError(RpcErrorEvent $event): void
    {
        $error = $event->rpcError;
        try {
            $method = $event->rpcRequest?->getMethod();
            $params = $event->rpcRequest?->getParams();
        } catch (Throwable) {
            $method = null;
            $params = null;
        }
        $this->logger->error($error->getMessage(), [
            'method' => $method,
            'params' => $params,
            'data'  => $error->getData(),
        ]);
    }
}