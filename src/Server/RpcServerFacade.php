<?php

namespace Ufo\JsonRpcBundle\Server;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\RpcObject\Rules\Validator\ConstraintsImposedException;
use Ufo\RpcObject\Rules\Validator\RpcValidator;
use Ufo\JsonRpcBundle\Server\RpcCache\RpcCacheService;
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

    protected ServiceLocator $serviceLocator;

    /**
     * @var RpcServer
     */
    protected RpcServer $rpcServer;

    /**
     * @var array
     */
    protected array $nsAliases = [];

    public function __construct(
        protected RouterInterface $router,
        protected IRpcSecurity $rpcSecurity,
        protected SerializerInterface $serializer,
        protected RpcValidator $validator,
        protected RpcCacheService $cache,
        #[TaggedIterator('ufo.rpc.service')]
        protected iterable $procedures,
        protected RpcMainConfig $rpcConfig,
        LoggerInterface $logger = null
    ) {
        $this->initServiceLocator();
        $this->rpcServer = new RpcServer($this->serializer, $this->serviceLocator, $this->validator, $this->rpcConfig,
            $logger);
        $this->init();
    }

    protected function initServiceLocator(): void
    {
        $this->serviceLocator = $this->cache->getServiceLocator();
    }

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
            try {
                $response = $this->cache->getCacheResponse($singleRequest);
            } catch (WrongWayException) {
                $response = $this->rpcServer->handleRpcRequest($singleRequest);
            }
            $this->checkError($singleRequest); // validation
            $this->cache->saveCacheResponse($singleRequest, $response);
        } catch (WrongWayException $e) {
            // error in request
            $response = $this->handleError($singleRequest->getError());
        } catch (\Exception $e) {
            $singleRequest->setError($e);
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

    /**
     * @throws WrongWayException
     */
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

        return $this->getServer()->handleError($e->getMessage(), $code, $data);
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

    public function getSecurity(): IRpcSecurity
    {
        return $this->rpcSecurity;
    }

}