<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\Info;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;

class ServiceHolder implements IServiceHolder
{
    protected const string SERVICE_CACHE_PREFIX = 'service_';

    const string TAG = 'rpc.service_holder';
    public function __construct(
        #[AutowireIterator(IServiceHolder::TAG)]
        protected iterable $holders,
        protected CacheItemPoolInterface $cache,
        protected ?ApiGroupContext $apiGroupContext = null,
    ) {}

    /**
     * @param string $serviceName
     * @param string $version
     * @return Service
     * @throws ServiceNotFoundException
     */
    public function getService(string $serviceName, string $version = Info::DEFAULT_VERSION): Service
    {
        return $this->cachedService($serviceName, $version);
    }

    /**
     * @throws ServiceNotFoundException
     */
    protected function cachedService(string $serviceName, string $version): Service
    {
        // Resolve membership before reading shared metadata. Custom holders keep
        // their existing lookup behavior and receive the same request context.
        $resolved = $this->apiGroupContext ? $this->findService($serviceName, $version) : null;
        $cacheKey = static::generateServiceCacheName($serviceName, $version);

        try {
            $service = $this->cache->getItem($cacheKey)->get();
            if ($service instanceof Service) {
                return $service;
            }
        } catch (Throwable) {}

        return $resolved ?? $this->findService($serviceName, $version);
    }

    private function findService(string $serviceName, string $version): Service
    {
        foreach ($this->holders as $holder) {
            try {
                return $holder->getService($serviceName, $version);
            } catch (ServiceNotFoundException) {}
        }
        throw new ServiceNotFoundException('Service "'.$serviceName.'" for version "'.$version.'" is not found on RPC Service Map');
    }

    public static function generateServiceCacheName(string $serviceName, string $version): string
    {
        return static::SERVICE_CACHE_PREFIX . $serviceName . '_' . $version;
    }

    /**
     * @param string $version
     * @throws WrongWayException
     */
    public function getServices(string $version = Info::DEFAULT_VERSION): array
    {
        throw new WrongWayException('Method "getServices" not implemented for "' . static::class . '"');
    }

    public function getVersions(): array
    {
        $versions = [];
        foreach ($this->holders as $holder) {
            $versions = array_merge($versions, $holder->getVersions());
        };
        return array_unique($versions);
    }

}