<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Ufo\JsonRpcBundle\Server\RpcCache\RpcCacheService;
use Ufo\RpcObject\Events\RpcEvent;
use Ufo\RpcObject\Events\RpcPostResponseEvent;

#[AsEventListener(RpcEvent::POST_RESPONSE, 'process', priority: 1000)]
class CacheRelationsEventListener
{

    public function __construct(
        protected RpcCacheService $cacheService,
    ) {}

    public function process(RpcPostResponseEvent $event): void
    {
        $this->cacheService->getCacheRelationDefinition($event->service);

        $this->cacheService->invalidateRelationsCache(
            $event->rpcRequest->getParams(),
            $event->rpcRequest->getId() ?? 'not_id'
        );

        $this->cacheService->warmupCache();

    }
}
