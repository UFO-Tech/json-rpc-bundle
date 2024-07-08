<?php

namespace Ufo\JsonRpcBundle\Server\RpcCache;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceLocator;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\Cache;
use Ufo\RpcObject\RpcCachedResponse;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

use function in_array;
use function is_null;
use function sprintf;

class RpcCacheService
{

    protected ?ServiceLocator $serviceLocator = null;

    public function __construct(
        protected CacheItemPoolInterface $cache,
        protected RpcMainConfig $rpcConfig,
    ) {}



    public function getCacheResponse(RpcRequest $singleRequest): RpcResponse
    {
        $cacheKey = sprintf("%s.%s", $singleRequest->getMethod(), $this->hashArrayData($singleRequest->getParams()));
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