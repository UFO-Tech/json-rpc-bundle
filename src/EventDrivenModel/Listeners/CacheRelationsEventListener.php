<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Ufo\JsonRpcBundle\Server\RpcCache\RpcCacheService;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPostResponseEvent;

#[AsEventListener(RpcEvent::POST_RESPONSE, 'process', priority: 1000)]
class CacheRelationsEventListener
{

    public function __construct(
        protected RpcCacheService $cacheService,
    ) {}

    public function process(RpcPostResponseEvent $event): void
    {
        $this->cacheService->getCacheRelationDefinition($event->service);

        $this->cacheService->addRelationsRequestToCache(
            $event->rpcRequest->getParams(),
            $event->rpcRequest->getId() ?? 'not_id'
        );

        $this->cacheService->warmupCache();

    }
}
