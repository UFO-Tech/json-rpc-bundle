<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use Deprecated;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\Info;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;

class ServiceHolder implements IServiceHolder
{
    /**
     * @deprecated Since v10.0, will be removed in v11.0. Services are no longer read from a PSR cache.
     * @todo Remove in version 11.0.0
     */
    protected const string SERVICE_CACHE_PREFIX = 'service_';

    const string TAG = 'rpc.service_holder';
    /**
     * @param CacheItemPoolInterface $cache Not used since v10.0, kept for backward compatibility.
     *                                      Will be removed in v11.0.
     */
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
        // No PSR cache lookup: nothing ever wrote these keys, so every request paid for a miss
        // (a cache.app round-trip), and a key without the API group could return another group's
        // implementation. ServiceMap already memoizes hydrated services within the process.
        return $this->findService($serviceName, $version);
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

    /**
     * @deprecated Since v10.0, will be removed in v11.0. Services are no longer read from a PSR cache.
     * @todo Remove in version 11.0.0
     */
    #[Deprecated]
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