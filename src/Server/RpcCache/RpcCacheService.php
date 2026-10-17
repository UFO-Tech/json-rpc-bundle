<?php

namespace Ufo\JsonRpcBundle\Server\RpcCache;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Server\RpcServer;
use Ufo\JsonRpcBundle\Server\ServiceMap\IServiceHolder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers\ChainServiceFiller;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\UfoReflectionProcedure;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\Cache;
use Ufo\RpcObject\RPC\CacheRelation;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RpcCachedResponse;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\SpecialRpcParamsEnum;

use function array_flip;
use function array_intersect_key;
use function array_keys;
use function array_map;
use function in_array;
use function is_int;
use function is_null;
use function sprintf;

class RpcCacheService
{
    const string RELATED_CACHE = 'related_cache';

    protected ?RelationCacheDefinitionDTO $definitionDTO = null;

    /**
     * @var UfoReflectionProcedure[]
     */
    protected array $reflections = [];

    protected RpcServer $localRpcServer;

    public function __construct(
        protected CacheItemPoolInterface $cache,
        protected RpcMainConfig $rpcConfig,
        protected ServiceMap $serviceMap,

        #[Autowire(service: IServiceHolder::LOCATOR)]
        protected ContainerInterface $serviceLocator,
        protected ChainServiceFiller $chainServiceFiller,
        protected RpcEventFactory $eventFactory,
    )
    {
        $this->localRpcServer = new RpcServer(
            $this->serviceMap,
            $this->eventFactory,
            $this
        );

    }

    /**
     * Reads the cache only for methods marked with #[Cache] for the current environment;
     * other methods cost no cache I/O at all.
     *
     * @throws WrongWayException when the method is not cacheable or the response is not cached
     */
    public function getCacheResponse(RpcRequest $singleRequest, ?Service $service = null): RpcResponse
    {
        $service ??= $this->findService($singleRequest);
        if (!$service || !$this->isCacheable($service)) {
            throw new WrongWayException();
        }

        $item = $this->cache->getItem($this->getCacheKey($singleRequest, $service));
        $cached = $item->isHit() ? $item->get() : null;
        if (!$cached instanceof RpcCachedResponse) {
            throw new WrongWayException();
        }

        return new RpcResponse($singleRequest->getId(), $cached->result);
    }

    public function saveCacheResponse(RpcRequest $singleRequest, RpcResponse $response, ?Service $service = null): void
    {
        $cache = $response->getCacheInfo();
        if (!$cache || !in_array($this->rpcConfig->environment, $cache->environments)) return;
        if (!$service ??= $this->findService($singleRequest)) return;

        $item = $this->cache->getItem($this->getCacheKey($singleRequest, $service));
        $item->set(new RpcCachedResponse($response->getResult()));
        $item->expiresAfter($cache->lifetimeSecond);
        $this->cache->save($item);
    }

    public function isCacheable(Service $service): bool
    {
        $cache = $service->getAttrCollection()->getAttribute(Cache::class);
        return $cache instanceof Cache && in_array($this->rpcConfig->environment, $cache->environments);
    }

    protected function findService(RpcRequest $request): ?Service
    {
        try {
            return $this->serviceMap->getService($request->getMethod(), $request->apiVersion ?? Info::DEFAULT_VERSION);
        } catch (Throwable) {
            return null;
        }
    }

    public function hashArrayData(array $data): string
    {
        $this->sortRecursive($data);
        $paramString = json_encode($data);

        return hash('md5', $paramString);
    }

    /**
     * Method name + implementation (version, class::method) + params.
     * The same implementation shares the cache between API groups (BFFs);
     * different implementations or versions under one method name never collide.
     */
    public function getCacheKey(RpcRequest $singleRequest, ?Service $service = null): string
    {
        $service ??= $this->findService($singleRequest);
        $implementation = $service
            ? $service->apiClassInfo->version.'|'.$service->getProcedureFQCN().'::'.$service->getMethodName()
            : '';

        return sprintf(
            "%s.%s.%s",
            $singleRequest->getMethod(),
            hash('md5', $implementation),
            $this->hashArrayData($singleRequest->getParams())
        );
    }

    public function getCacheRelationDefinition(Service $service): RelationCacheDefinitionDTO
    {
        $dto = new RelationCacheDefinitionDTO();
        /**
         * @var CacheRelation[] $relations
         */
        $relations = $service->getAttrCollection()->getAttributes(CacheRelation::class);
        foreach ($relations as $relation) {
            $class = $relation->serviceFQCN;
            try {
                $procedure = $this->serviceLocator->get($class);

                $reflection = new UfoReflectionProcedure(
                    $procedure,
                    $this->chainServiceFiller
                );
                $dto->addServices($reflection->getMethods($relation->methods));
                if ($relation->warmUp) {
                    array_map(fn(Service $service) => $dto->addWarmUpsMethod($service->name), $reflection->getMethods
                    ($relation->methods));
                }
            } catch (Throwable) {}
        }
        $this->definitionDTO = $dto;
        return $dto;
    }

    public function addRelationsRequestToCache(array $params, string $id, bool $deleteCache = true): void
    {
        $this->iterateRelationsRequest($params, $id, function (RpcRequest $request, Service $relatedService) use ($deleteCache) {
            $this->definitionDTO->checkWarmUpsMethod($relatedService, $request);

            if ($deleteCache) {
                $this->deleteRequestCache($request, $relatedService);
            }
        });
    }

    public function warmupCache(): void
    {
        if (!$this->definitionDTO) return;

        foreach ($this->definitionDTO->getWarmupRequests() as $warmupRequest) {
            try {
                $this->localRpcServer->handle($warmupRequest);
            } catch (Throwable) {}
        }
    }

    public function sortRecursive(array &$arr): void {
        if (array_all($arr, fn ($item, $key) => !is_int($key))) {
            ksort($arr);
        } else {
            sort($arr);
        }

        foreach ($arr as &$value) {
            if (is_array($value)) {
                $this->sortRecursive($value);
            }
        }
    }

    public function warmCache(string $class, array $methods, array $params, bool $deleteCache = true): RelationCacheDefinitionDTO
    {
        $dto = $this->addReflectionServices($class, $methods);
        $this->addRelationsRequestToCache(
            $params,
            'not_id',
            $deleteCache
        );
        $this->warmupCache();

        return $dto;
    }

    public function deleteRelationCache(string $class, array $methods, array $params): void
    {
        $this->addReflectionServices($class, $methods);
        $this->iterateRelationsRequest($params, 'not_id', function (RpcRequest $request, Service $relatedService) {
            $this->deleteRequestCache($request, $relatedService);
        });
    }

    protected function addReflectionServices(string $class, array $methods): RelationCacheDefinitionDTO
    {
        $dto = new RelationCacheDefinitionDTO();
        try {
            if (!($reflection = $this->reflections[$class] ?? false)) {
                $procedure = $this->serviceLocator->get($class);
                $reflection = new UfoReflectionProcedure(
                    $procedure,
                    $this->chainServiceFiller
                );
                $this->reflections[$class] = $reflection;
            }
            $dto->addServices($reflection->getMethods($methods));
            array_map(fn(Service $service) => $dto->addWarmUpsMethod($service->name), $reflection->getMethods($methods));
        } catch (Throwable) {}
        $this->definitionDTO = $dto;
        return $dto;
    }

    public function deleteRequestCache(RpcRequest $request, ?Service $service = null): void
    {
        $this->cache->deleteItem($this->getCacheKey($request, $service));
    }

    /**
     * @param callable{RpcRequest, Service} $callback
     */
    protected function iterateRelationsRequest(array $params, string $id, callable $callback): void
    {
        if (!$this->definitionDTO) return;
        foreach ($this->definitionDTO->getServices() as $i => $relatedService) {
            try {
                $relatedParamsKeys = array_keys($relatedService->getParams());
                $refreshParams = array_intersect_key($params, array_flip($relatedParamsKeys));

                $request = new RpcRequest(
                    static::RELATED_CACHE . '.' . $id . '.' . $i,
                    $relatedService->name,
                    [
                        ...$refreshParams,
                        ...[SpecialRpcParamsEnum::PREFIX => [SpecialRpcParamsEnum::IGNORE_CACHE->value => true]]
                    ]
                );

                $callback($request, $relatedService);
            } catch (\Throwable) {}
        }
    }
}
