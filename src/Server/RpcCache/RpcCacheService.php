<?php

namespace Ufo\JsonRpcBundle\Server\RpcCache;

use Deprecated;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Server\RpcServer;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers\ChainServiceFiller;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\UfoReflectionProcedure;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceLocator;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\CacheRelation;
use Ufo\RpcObject\RpcCachedResponse;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

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

    protected ServiceMap $localServiceMap;
    protected RpcServer $localRpcServer;

    public function __construct(
        protected CacheItemPoolInterface $cache,
        protected RpcMainConfig $rpcConfig,
        protected ServiceLocator $serviceLocator,
        protected ChainServiceFiller $chainServiceFiller,
        protected RpcEventFactory $eventFactory,
    )
    {
        $this->localServiceMap = new ServiceMap('local', $this->rpcConfig);
        $this->localRpcServer = new RpcServer(
            $this->localServiceMap,
            $this->eventFactory,
            $this
        );

    }

    public function getCacheResponse(RpcRequest $singleRequest): RpcResponse
    {
        $cacheKey = $this->getCacheKey($singleRequest);
        /**
         * @var RpcCachedResponse $response
         */
        $response = $this->cache->get($cacheKey, function (ItemInterface $item) {
            return null;
        });
        if (is_null($response)) {
            $this->cache->delete($cacheKey);
            throw new WrongWayException();
        }

        return new RpcResponse($singleRequest->getId(), $response->result);
    }

    public function saveCacheResponse(RpcRequest $singleRequest, RpcResponse $response): void
    {
        if ($cache = $response->getCacheInfo()) {
            if (in_array($this->rpcConfig->environment, $cache->environments)) {
                $cacheKey = $this->getCacheKey($singleRequest);
                $this->cache->delete($cacheKey);
                $this->cache->get($cacheKey, function (ItemInterface $item) use ($response, $cache) {
                    $item->expiresAfter($cache->lifetimeSecond);

                    return new RpcCachedResponse($response->getResult());
                });
            }
        }
    }

    public function hashArrayData(array $data): string
    {
        $this->sortRecursive($data);
        $paramString = json_encode($data);

        return hash('md5', $paramString);
    }

    /**
     * @param RpcRequest $singleRequest
     * @return string
     */
    public function getCacheKey(RpcRequest $singleRequest): string
    {
        return sprintf("%s.%s", $singleRequest->getMethod(), $this->hashArrayData($singleRequest->getParams()));
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
                    $this->rpcConfig->docsConfig,
                    $this->chainServiceFiller
                );
                $dto->addServices($reflection->getMethods($relation->methods));
                if ($relation->warmUp) {
                    array_map(fn(Service $service) => $dto->addWarmUpsMethod($service->getName()), $reflection->getMethods($relation->methods));
                }
            } catch (\Throwable) {}
        }
        $this->definitionDTO = $dto;
        return $dto;
    }

    public function addRelationsRequestToCache(array $params, string $id, bool $deleteCache = true): void
    {
        if (!$this->definitionDTO) return;
        foreach ($this->definitionDTO->getServices() as $i => $relatedService) {
            try {
                $relatedParamsKeys = array_keys($relatedService->getParams());
                $refreshParams = array_intersect_key($params, array_flip($relatedParamsKeys));

                $request = new RpcRequest(
                    static::RELATED_CACHE . '.' . $id . '.' . $i,
                    $relatedService->getName(),
                    $refreshParams
                );
                $this->definitionDTO->checkWarmUpsMethod($relatedService, $request);

                if ($deleteCache) {
                    $cacheKey = $this->getCacheKey($request);
                    $this->cache->delete($cacheKey);
                }
            } catch (\Throwable) {}
        }
    }

    /**
     * @deprecated Use addRelationsRequestToCache instead
     */
    public function invalidateRelationsCache(array $params, string $id): void
    {
        $this->addRelationsRequestToCache($params, $id);
    }

    public function warmupCache(): void
    {
        if (!$this->definitionDTO) return;
        foreach ($this->definitionDTO->getServices() as $service) {
            try {
                $this->localServiceMap->addService($service);
            } catch (\Throwable) {}
        }

        foreach ($this->definitionDTO->getWarmupRequests() as $warmupRequest) {
            try {
                $this->localRpcServer->handle($warmupRequest);
            } catch (\Throwable) {}
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
        $dto = new RelationCacheDefinitionDTO();
        try {
            if (!($reflection = $this->reflections[$class] ?? false)) {
                $procedure = $this->serviceLocator->get($class);
                $this->reflections[$class] = $procedure;

                $reflection = new UfoReflectionProcedure(
                    $procedure,
                    $this->rpcConfig->docsConfig,
                    $this->chainServiceFiller
                );
            }
            $dto->addServices($reflection->getMethods($methods));
            array_map(fn(Service $service) => $dto->addWarmUpsMethod($service->getName()), $reflection->getMethods($methods));
        } catch (\Throwable) {}
        $this->definitionDTO = $dto;

        $this->addRelationsRequestToCache(
            $params,
            'not_id',
            $deleteCache
        );
        $this->warmupCache();

        return $dto;
    }
}