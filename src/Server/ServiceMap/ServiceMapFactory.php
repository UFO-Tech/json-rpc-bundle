<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use ReflectionException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\ItemInterface;
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
    const string CACHE_SM = 'rps.service_map';

    protected ServiceLocator $serviceLocator;
    protected ServiceMap $serviceMap;

    /**
     * @throws RpcInternalException
     */
    public function __construct(
        protected CacheItemPoolInterface $cache,
        protected RpcMainConfig $rpcConfig,
        protected SerializerInterface $serializer,
        #[AutowireIterator('ufo.rpc.service')]
        protected iterable $procedures,
        protected RouterInterface $router,
        #[AutowireLocator('ufo.rpc.service')]
        protected ContainerInterface $locator,
        protected ChainServiceFiller $chainServiceFiller,

    ) {
        $this->buildServiceLocator();
    }

    public function getServiceLocator(): ServiceLocator
    {
        return $this->serviceLocator;
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
                $serviceData = $this->serializer->decode($state,'json');
                $this->serviceMap->addService(Service::fromArray($serviceData));
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
                $item->expiresAfter(31536000);
                try {
                    $states = [];
                    foreach ($this->serviceMap->getServices() as $service) {
                        $state = $this->serializer->serialize($service, 'json', ['service' => $service]);
                        $states[$service->getName()] = $state;
                    }
                    $item->set($states);
                    return $states;
                } catch (\Throwable $e) {
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
        return $smd;
    }

    protected function initServices(): void
    {
        $this->serviceLocator = new ServiceLocator($this->locator);
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