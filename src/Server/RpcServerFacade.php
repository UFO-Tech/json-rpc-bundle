<?php
namespace Ufo\JsonRpcBundle\Server;


use Laminas\Json\Server\Error;
use Laminas\Json\Server\Response;
use Laminas\Json\Server\Response\Http;
use phpDocumentor\Reflection\Types\Iterable_;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\Exceptions\AbstractJsonRpcBundleException;
use Ufo\JsonRpcBundle\Exceptions\RpcBadRequestException;
use Ufo\JsonRpcBundle\Exceptions\WrongWayException;
use Ufo\JsonRpcBundle\Interfaces\IFacadeRpcServer;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use \Laminas\Json\Server\Smd;

class RpcServerFacade implements IFacadeRpcServer
{
    /**
     * @var RpcServer
     */
    protected RpcServer $rpcServer;
    
    protected RpcRequestObject $requestObject;

    /**
     * @var array
     */
    protected array $nsAliases = [];

    /**
     * @param RouterInterface $router
     * @param IRpcSecurity $rpcSecurity
     * @param string $environment
     * @param SerializerInterface $serializer
     * @param IRpcService[] $procedures
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        protected RouterInterface $router,
        protected IRpcSecurity $rpcSecurity,
        protected string $environment,
        protected SerializerInterface $serializer,
        #[TaggedIterator('ufo.rpc.service')] iterable $procedures,
        LoggerInterface $logger = null
    )
    {
        $this->rpcServer = new RpcServer($this->serializer, $logger);
        $this->init();
        $this->setProcedures($procedures);
    }

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
     * @param mixed $argv
     * @return $this
     */
    public function addProcedure(IRpcService $procedure, string $namespace = '', mixed $argv = null): static
    {
        if (empty($namespace) && is_object($procedure)) {
            $className = explode('\\', get_class($procedure));
            $namespace = array_pop($className);
            if ($namespace == 'PingProcedure') {
                $namespace = '';
            }
            $this->setNamespaceAliasesForProxyAccess($procedure, $namespace);
        }
        $this->rpcServer->setClass($procedure, $namespace, $argv);
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
    public function handle(RpcRequestObject $singleRequest): RpcResponseObject
    {
        $this->rpcServer->clearRequestAndResponse();
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

    protected function handleError(\Throwable $e): RpcResponseObject
    {
        $code = ($e instanceof AbstractJsonRpcBundleException) ? $e->getCode() : AbstractJsonRpcBundleException::DEFAULT_CODE;
        return $this->getServer()->handleError($e->getMessage(), $code, $e);
    }
    
    /**
     * @param $procedure
     * @param $namespace
     * @return void
     */
    protected function setNamespaceAliasesForProxyAccess($procedure, $namespace)
    {
        foreach (class_implements($procedure) as $interface) {
            $this->addNsAlias($namespace, $interface);
        }
    }

    /**
     * @return mixed
     */
    public function getServiceMap(): mixed
    {
        try {
            $this->rpcSecurity->isValidRequest();
            $this->rpcServer->setTarget($this->router->generate(ApiController::API_ROUTE))
                ->setEnvelope(Smd::ENV_JSONRPC_2);
            $response = $this->rpcServer->getServiceMap();
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
        return isset($this->nsAliases[$alias]) ? $this->nsAliases[$alias] : $alias;
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