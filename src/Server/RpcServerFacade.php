<?php
namespace Ufo\JsonRpcBundle\Server;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceLocator;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcTokenNotFoundInHeaderException;
use Ufo\RpcError\WrongWayException;
use Ufo\JsonRpcBundle\Interfaces\IFacadeRpcServer;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

class RpcServerFacade implements IFacadeRpcServer
{
    /**
     * @var RpcServer
     */
    protected RpcServer $rpcServer;
    
    /**
     * @var array
     */
    protected array $nsAliases = [];

    /**
     * @param RouterInterface $router
     * @param IRpcSecurity $rpcSecurity
     * @param string $environment
     * @param SerializerInterface $serializer
     * @param iterable $procedures
     * @param LoggerInterface|null $logger
     * @throws \ReflectionException
     */
    public function __construct(
        protected ServiceLocator $serviceLocator,
        protected RouterInterface $router,
        protected IRpcSecurity $rpcSecurity,
        protected string $environment,
        protected SerializerInterface $serializer,
        #[TaggedIterator('ufo.rpc.service')] iterable $procedures,
        LoggerInterface $logger = null
    )
    {
        $this->rpcServer = new RpcServer($this->serializer, $this->serviceLocator, $logger);
        $this->init();
        $this->setProcedures($procedures);
    }

    /**
     * @throws RpcTokenNotFoundInHeaderException
     * @throws RpcInvalidTokenException
     */
    protected function init(): void
    {
        $this->rpcSecurity->isValidRequest();
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

            if ($singleRequest->hasError()) {
                throw new WrongWayException();
            }

            $response = $this->rpcServer->handleRpcRequest($singleRequest);
        } catch (WrongWayException) {
            // error in request
            $response = $this->handleError($singleRequest->getError());
        } catch (\Exception $e) {
            $response = $this->handleError($e);
        }
        $singleRequest->setResponse($response);
        return $response;
    }

    protected function handleError(\Throwable $e): RpcResponse
    {
        $code = ($e instanceof AbstractRpcErrorException) ? $e->getCode() : AbstractRpcErrorException::DEFAULT_CODE;
        return $this->getServer()->handleError($e->getMessage(), $code, $e);
    }
    
    /**
     * @param $procedure
     * @param $namespace
     * @return void
     */
    protected function setNamespaceAliasesForProxyAccess($procedure, $namespace): void
    {
        foreach (class_implements($procedure) as $interface) {
            $this->addNsAlias($namespace, $interface);
        }
    }

    public function getServiceMap(): RpcResponse|ServiceLocator
    {
        try {
            $this->rpcSecurity->isValidRequest();
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

        return  implode('.', array_diff($n, ['']));
    }

    public function getSecurity(): IRpcSecurity
    {
        return $this->rpcSecurity;
    }

}