<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\UfoReflectionProcedure;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\Cache;

use function is_null;

class ServiceMapFactory
{
    const string CACHE_SL = 'rps.service_locator';

    protected ServiceLocator $serviceLocator;

    /**
     * @var IRpcService[]
     */
    protected array $procedures = [];


    public function __construct(
        protected CacheItemPoolInterface $cache,
        protected RpcMainConfig $rpcConfig,
        protected SerializerInterface $serializer,
        #[TaggedIterator('ufo.rpc.service')]
        iterable $procedures,
        protected RouterInterface $router
    ) {
        foreach ($procedures as $procedure) {
            $this->procedures[$procedure::class] = $procedure;
        }
        $this->buildServiceLocator();
    }

    public function getServiceLocator(): ServiceLocator
    {
        return $this->serviceLocator;
    }

    protected function buildServiceLocator(): void
    {
        $this->serviceLocator = $this->defaultSL();
        try {
            if ($this->rpcConfig->environment !== Cache::ENV_PROD) {
                throw new WrongWayException();
            }
            $states = $this->cache->get(static::CACHE_SL, function (ItemInterface $item) {return [];});
            if (empty($states)) {
                throw new WrongWayException();
            }

            foreach ($states as $state) {
                $procedure = $this->procedures[$state['procedure']] ?? null;
                if (is_null($procedure)) {
                    continue;
                }
                $state['procedure'] = $procedure;
                $service = $this->serializer->denormalize($state, Service::class);
                $this->serviceLocator->addService($service);
            }
        } catch (WrongWayException) {
            $this->setProcedures($this->procedures);
        }
    }

    protected function saveServiceLocator(): void
    {
        if ($this->rpcConfig->environment !== Cache::ENV_PROD) {
            return;
        }
        $states = $this->cache->get(static::CACHE_SL, function () {return [];});
        if (!empty($states)) {
            return;
        }
        $this->cache->delete(static::CACHE_SL);
        $this->cache->get(static::CACHE_SL, function (ItemInterface $item) {
            $item->expiresAfter(31536000);
            try {
                $states = [];
                foreach ($this->serviceLocator->getServices() as $service) {
                    $state = $this->serializer->normalize(
                        $service,
                        context: [AbstractNormalizer::IGNORED_ATTRIBUTES => ['procedure']]
                    );
                    $state['procedure'] = $service->getProcedure()::class;;
                    $states[$service->getName()] = $state;
                }
                $item->set($states);
                return $states;
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    protected function defaultSL(): ServiceLocator
    {
        $sl = new ServiceLocator($this->rpcConfig);
        $sl->setTarget($this->router->generate(ApiController::API_ROUTE));
        return $sl;
    }

    public function __destruct()
    {
        $this->saveServiceLocator();
    }

    /**
     * @param IRpcService $procedure
     * @return $this
     */
    public function addProcedure(IRpcService $procedure): static
    {
        $reflection = new UfoReflectionProcedure($procedure, $this->serializer, $this->rpcConfig->docsConfig);
        foreach ($reflection->getMethods() as $service) {
            $this->serviceLocator->addService($service);
        }
        return $this;
    }

    /**
     * @param IRpcService[] $procedures
     * @return void
     */
    public function setProcedures(iterable $procedures): void
    {
        foreach ($procedures as $procedure) {
            $this->addProcedure($procedure);
        }
    }
}