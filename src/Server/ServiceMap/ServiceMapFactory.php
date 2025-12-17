<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use Psr\Cache\CacheItemPoolInterface;
use ReflectionException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers\ChainServiceFiller;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\UfoReflectionProcedure;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\Cache;

class ServiceMapFactory
{
    protected const int CACHE_LIFETIME  = 31536000;
    const string CACHE_SM = 'rpc.service_map';

    protected ServiceLocator $serviceLocator;
    protected ServiceMap $serviceMap;

    /**
     * @throws RpcInternalException
     */
    public function __construct(
        protected CacheItemPoolInterface $cache,
        protected RpcMainConfig $rpcConfig,
        protected SerializerInterface $serializer,
        #[AutowireIterator(IRpcService::TAG)]
        protected iterable $procedures,
        protected RouterInterface $router,
        protected ChainServiceFiller $chainServiceFiller,

    ) {
        $this->buildServiceLocator();
    }

    public function getServiceMap(): ServiceMap
    {
        return $this->serviceMap;
    }

    protected function buildServiceLocator(): void
    {
        $this->initServices();
        try {
            if ($this->rpcConfig->environment !== Cache::ENV_PROD) {
                throw new WrongWayException();
            }
            $states = $this->fromCache();

            foreach ($states as $state) {
                $this->serviceMap->addService($state);
            }
        } catch (WrongWayException) {
            $this->setProcedures();
        } catch (ReflectionException $e) {
            throw new RpcInternalException('ServiceMap not created', previous:  $e);
        }
    }

    protected function saveServiceMap(): void
    {
        if ($this->rpcConfig->environment !== Cache::ENV_PROD) {
            return;
        }
        try {
            $this->fromCache();
        } catch (WrongWayException) {
            $this->cache->get(static::CACHE_SM, function (ItemInterface $item) {
                $item->expiresAfter(static::CACHE_LIFETIME);
                try {
                    $states = [];
                    foreach ($this->serviceMap->getServices() as $service) {
                        $serviceCacheName = ServiceHolder::generateServiceCacheName($service->getName());
                        $this->cache->delete($serviceCacheName);
                        $this->cache->get(
                            $serviceCacheName,
                            function (ItemInterface $item) use ($service) {
                                $item->expiresAfter(static::CACHE_LIFETIME);
                                return $service;
                            }
                        );
                        $states[$service->getName()] = $service;
                    }
                    $item->set($states);
                    return $states;
                } catch (Throwable $e) {
                    return [];
                }
            });
        }
    }

    /**
     * @throws WrongWayException
     */
    protected function fromCache(): array
    {
        if (empty($smd = $this->cache->get(static::CACHE_SM, function () { return []; }))) {
            $this->cache->delete(static::CACHE_SM);
            throw new WrongWayException();
        }
        $this->serviceMap->setFromCacheTrue();
        return $smd;
    }

    protected function initServices(): void
    {
        $this->serviceMap = new ServiceMap(
            $this->router->generate(ApiController::API_ROUTE),
            $this->rpcConfig
        );
    }

    public function __destruct()
    {
        $this->saveServiceMap();
    }

    /**
     * @param IRpcService $procedure
     * @return $this
     */
    public function addProcedure(IRpcService $procedure): static
    {
        $reflection = new UfoReflectionProcedure(
            $procedure,
            $this->rpcConfig->docsConfig,
            $this->chainServiceFiller
        );
        foreach ($reflection->getMethods() as $service) {
            $this->serviceMap->addService($service);
        }
        return $this;
    }

    protected function setProcedures(): void
    {
        foreach ($this->procedures as $procedure) {
            $this->addProcedure($procedure);
        }
    }

}