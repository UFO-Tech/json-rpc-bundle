<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Locker\LockerService;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreExecuteEvent;
use Ufo\RpcObject\Transformer\RpcResponseContextBuilder;


#[AsEventListener(RpcEvent::PRE_EXECUTE, 'lock', priority: -100)]
class LockEventListener
{

    public function __construct(
        protected LockerService $lockerService,
        protected RpcEventFactory $eventFactory,
        protected RpcResponseContextBuilder $contextBuilder,
    ) {}

    public function lock(RpcPreExecuteEvent $event): void
    {
        if ($event->rpcRequest->hasError()) {
            $this->eventFactory->fireError($event->rpcRequest, $event->rpcRequest->getError());
            $event->stopPropagation();
            return;
        }
        $this->lockerService->lock($event->rpcRequest, $event->service);
    }

}
