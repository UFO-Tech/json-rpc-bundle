<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;

class ServiceHolder implements IServiceHolder
{
    protected const string SERVICE_CACHE_PREFIX = 'service_';

    const string TAG = 'rpc.service_holder';
    public function __construct(
        #[AutowireIterator(IServiceHolder::TAG)]
        protected iterable $holders,
        protected CacheItemPoolInterface $cache,
    ) {}

    /**
     * @param string $serviceName
     * @return Service
     * @throws ServiceNotFoundException
     */
    public function getService(string $serviceName): Service
    {
        return $this->cachedService($serviceName);
    }

    /**
     * @throws ServiceNotFoundException
     */
    protected function cachedService(string $serviceName): Service
    {
        $cacheKey = static::generateServiceCacheName($serviceName);

        try {
            return $this->cache->getItem($cacheKey)->get();
        } catch (\Throwable $e) {
            foreach ($this->holders as $holder) {
                /**
                 * @var IServiceHolder $holder
                 */
                try {
                    return $holder->getService($serviceName);
                } catch (ServiceNotFoundException) {}
            };
            throw new ServiceNotFoundException('Service "' . $serviceName . '" is not found on RPC Service Map');
        }
    }

    public static function generateServiceCacheName(string $serviceName): string
    {
        return static::SERVICE_CACHE_PREFIX . $serviceName;
    }
}