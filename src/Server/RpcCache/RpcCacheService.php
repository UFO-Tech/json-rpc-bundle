<?php

namespace Ufo\JsonRpcBundle\Server\RpcCache;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use TypeError;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceLocator;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\Cache;
use Ufo\RpcObject\RpcCachedResponse;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

use function in_array;
use function is_null;
use function sprintf;

class RpcCacheService implements CacheInterface
{
    const CACHE_SL = 'rps.service_locator';

    protected ?ServiceLocator $serviceLocator = null;

    public function __construct(
        protected CacheInterface $cache,
        protected RpcMainConfig $rpcConfig,
    ) {}

    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        return $this->cache->get($key, $callback, $beta, $metadata);
    }

    public function delete(string $key): bool
    {
        return $this->cache->delete($key);
    }

    public function getServiceLocator(): ServiceLocator
    {
        if ($this->rpcConfig->environment === Cache::ENV_PROD) {
            try {
                $this->serviceLocator = $this->get(static::CACHE_SL, function (ItemInterface $item) {
                    return $this->defaultSL();
                });
            } catch (TypeError) {
                $this->delete(static::CACHE_SL);
                $this->serviceLocator = $this->getServiceLocator();
            }
        } else {
            $this->serviceLocator = $this->defaultSL();
        }

        return $this->serviceLocator;
    }

    protected function saveServiceLocator(): void
    {
        if ($this->rpcConfig->environment === Cache::ENV_PROD) {
            $this->delete(static::CACHE_SL);
            $this->get(static::CACHE_SL, function (ItemInterface $item) {
                return $this->serviceLocator;
            });
        }
    }

    protected function defaultSL(): ServiceLocator
    {
        return new ServiceLocator($this->rpcConfig->docsConfig->keyForMethods);
    }

    public function __destruct()
    {
        $this->saveServiceLocator();
    }

    public function getCacheResponse(RpcRequest $singleRequest): RpcResponse
    {
        $cacheKey = sprintf("%s.%s", $singleRequest->getMethod(), $this->hashArrayData($singleRequest->getParams()));
        /**
         * @var RpcCachedResponse $response
         */
        $response = $this->get($cacheKey, function (ItemInterface $item) {
            return null;
        });
        if (is_null($response)) {
            $this->delete($cacheKey);
            throw new WrongWayException();
        }

        return new RpcResponse($singleRequest->getId(), $response->result);
    }

    public function saveCacheResponse(RpcRequest $singleRequest, RpcResponse $response): void
    {
        if ($cache = $response->getCacheInfo()) {
            if (in_array($this->rpcConfig->environment, $cache->environments)) {
                $cacheKey = sprintf("%s.%s", $singleRequest->getMethod(),
                    $this->hashArrayData($singleRequest->getParams()));
                $this->cache->delete($cacheKey);
                $this->cache->get($cacheKey, function (ItemInterface $item) use ($response, $cache) {
                    $item->expiresAfter($cache->lifetimeSecond);

                    return new RpcCachedResponse($response->getResult());
                });
            }
        }
    }

    protected function hashArrayData(array $data): string
    {
        ksort($data);
        $paramString = json_encode($data);

        return hash('sha256', $paramString);
    }

}