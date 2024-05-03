<?php

namespace Ufo\JsonRpcBundle\Server;


use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use ReflectionException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use TypeError;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\Exceptions\ConstraintsImposedException;
use Ufo\JsonRpcBundle\Interfaces\IRpcValidator;
use Ufo\JsonRpcBundle\Server\ServiceMap\CachedServiceLocator;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceLocator;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcTokenNotFoundInHeaderException;
use Ufo\RpcError\WrongWayException;
use Ufo\JsonRpcBundle\Interfaces\IFacadeRpcServer;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use function get_class;

class RpcServerFacade implements IFacadeRpcServer
{
    const CACHE_SL = 'rps.service_locator';
    /**
     * @var RpcServer
     */
    protected RpcServer $rpcServer;
    /**
     * @var array
     */
    protected array $nsAliases = [];

    /**
     * @throws RpcTokenNotFoundInHeaderException
     * @throws ReflectionException
     * @throws RpcInvalidTokenException
     */
    public function __construct(
        protected ServiceLocator $serviceLocator,
        protected RouterInterface $router,
        protected IRpcSecurity $rpcSecurity,
        protected string $environment,
        protected SerializerInterface $serializer,
        protected IRpcValidator $validator,
        protected CacheInterface $cache,
        #[TaggedIterator('ufo.rpc.service')]
        protected iterable $procedures,
        LoggerInterface $logger = null
    ) {
        $this->rewriteServiceLocator();
        $this->rpcServer = new RpcServer(
            $this->serializer, $this->serviceLocator, $this->validator, $logger
        );
        $this->init();

    }

    protected function rewriteServiceLocator(): void
    {
        if ($this->environment === 'prod') {
            try {
                $this->serviceLocator = $this->cache->get(static::CACHE_SL, function (ItemInterface $item) {
                    return new ServiceLocator();
                });
            } catch (TypeError) {
                $this->cache->delete(static::CACHE_SL);
                $this->rewriteServiceLocator();
            }
        }
    }

    /**
     * @throws RpcTokenNotFoundInHeaderException
     * @throws RpcInvalidTokenException
     */
    protected function init(): void
    {
        if ($this->serviceLocator->empty()) {
            $this->setProcedures($this->procedures);
        }
    }

    /**
     * @return RpcServer Server
     */
    public function getServer(): RpcServer
    {
        return $this->rpcServer;
    }

    /**
     * @param IRpcService $procedure
     * @param string $namespace
     * @return $this
     */
    public function addProcedure(IRpcService $procedure, string $namespace = ''): static
    {
        $className = explode('\\', get_class($procedure));
        if (empty($namespace)) {
            $namespace = array_pop($className);
            if ($namespace == 'PingProcedure') {
                $namespace = '';
            }
            $this->setNamespaceAliasesForProxyAccess($procedure, $namespace);
        }
        $this->rpcServer->addProcedure($procedure);
        return $this;
    }

    /**
     * @param IRpcService[] $procedures
     * @return void
     * @throws \ReflectionException
     */
    public function setProcedures(iterable $procedures): void
    {
        foreach ($procedures as $procedure) {
            $this->addProcedure($procedure);
        }
    }

    /**
     * @return mixed
     */
    public function handle(RpcRequest $singleRequest): RpcResponse
    {
        $this->rpcServer->newRequest($singleRequest);
        try {
            $this->rpcSecurity->isValidRequest();
            $this->checkError($singleRequest); // security
            $response = $this->rpcServer->handleRpcRequest($singleRequest);
            $this->checkError($singleRequest); // validation
        } catch (WrongWayException $e) {
            // error in request
            $response = $this->handleError($singleRequest->getError());
        } catch (\Exception $e) {
            $response = $this->handleError($e);
        }
        $singleRequest->setResponse($response);
        return $response;
    }

    /**
     * @throws RpcTokenNotFoundInHeaderException
     * @throws RpcInvalidTokenException
     */
    public function handleSmRequest(): ServiceLocator
    {
        $this->rpcSecurity->isValidRequest();
        return $this->getServiceMap();
    }

    protected function checkError(RpcRequest $singleRequest): void
    {
        if ($singleRequest->hasError()) {
            throw new WrongWayException();
        }
    }

    protected function handleError(\Throwable $e): RpcResponse
    {
        $code = ($e instanceof AbstractRpcErrorException) ? $e->getCode() : AbstractRpcErrorException::DEFAULT_CODE;
        $data = ($e instanceof ConstraintsImposedException) ? $e->getConstraintsImposed() : $e;
        return $this->getServer()
                    ->handleError($e->getMessage(), $code, $data)
        ;
    }

    /**
     * @param $procedure
     * @param $namespace
     * @return void
     */
    protected function setNamespaceAliasesForProxyAccess($procedure, $namespace): void
    {
        $this->addNsAlias($namespace, get_class($procedure));
    }

    public function getServiceMap(): RpcResponse|ServiceLocator
    {
        try {
            $serviceLocator = $this->rpcServer->getServiceLocator();
            $serviceLocator->setTarget($this->router->generate(ApiController::API_ROUTE));
            $response = $serviceLocator;
        } catch (\Exception $e) {
            $response = $this->handleError($e);
        }
        return $response;
    }

    /**
     * @param string $alias
     * @param string $namespace
     * @return $this
     */
    public function addNsAlias(string $namespace, string $alias): static
    {
        $this->nsAliases[$alias] = $namespace;
        return $this;
    }

    /**
     * @return array
     */
    public function getNsAliases(): array
    {
        return $this->nsAliases;
    }

    /**
     * @param string $alias
     * @return string
     */
    public function checkNsAlias(string $alias): string
    {
        return $this->nsAliases[$alias] ?? $alias;
    }

    /**
     * @param string $methodName
     * @return string
     */
    protected function checkNsAliasFromMethodName(string $methodName): string
    {
        $n = explode('.', $methodName);
        if (count($n) != 2) {
            return $this->checkNsAlias($methodName);
        }
        $n[0] = $this->checkNsAlias($n[0]);
        return implode('.', array_diff($n, ['']));
    }

    public function getSecurity(): IRpcSecurity
    {
        return $this->rpcSecurity;
    }

    public function __destruct()
    {
        if ($this->environment === 'prod') {
            $this->cache->delete(static::CACHE_SL);
            $this->cache->get(static::CACHE_SL, function (ItemInterface $item) {
                return $this->serviceLocator;
            });
        }
    }
}