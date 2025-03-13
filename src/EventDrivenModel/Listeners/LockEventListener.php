<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Locker\LockerService;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceLocator;
use Ufo\RpcObject\Events\RpcEvent;
use Ufo\RpcObject\Events\RpcPreExecuteEvent;
use Ufo\RpcObject\Transformer\RpcResponseContextBuilder;


#[AsEventListener(RpcEvent::PRE_EXECUTE, 'lock', priority: -100)]
class LockEventListener
{

    public function __construct(
        protected LockerService $lockerService,
        #[Autowire('kernel.environment')]
        protected string $environment,
        protected RpcEventFactory $eventFactory,
        protected ServiceLocator $serviceLocator,
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
