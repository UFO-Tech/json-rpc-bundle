<?php
/**
 * Created by PhpStorm.
 * User: ashterix
 * Date: 26.09.16
 * Time: 19:04
 */

namespace Ufo\JsonRpcBundle\Facade;


use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\Exceptions\BadRequestException;
use Ufo\JsonRpcBundle\Facade\Interfaces\IFacadeJsonRpcServer;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Laminas\Json\Server\Error;
use Laminas\Json\Server\Response\Http;
use Ufo\JsonRpcBundle\Server\UfoZendServer;

class ZendJsonRpcServerFacade implements IFacadeJsonRpcServer
{
    /**
     * @var UfoZendServer
     */
    protected UfoZendServer $zendServer;

    /**
     * @var array
     */
    protected array $nsAliases = [];

    /**
     * ZendJsonRpcServerFacade constructor.
     * @param RouterInterface $router
     * @param IRpcSecurity $rpcSecurity
     * @param string $environment
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        protected RouterInterface $router,
        protected IRpcSecurity $rpcSecurity,
        protected string $environment,
        LoggerInterface $logger = null
    )
    {
        $this->zendServer = new UfoZendServer($logger);
    }

    /**
     * @return UfoZendServer JsonRpc Server
     */
    public function getServer(): UfoZendServer
    {
        return $this->zendServer;
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
        $this->zendServer->setClass($procedure, $namespace, $argv);
        return $this;
    }

    /**
     * @return mixed
     */
    public function handle()
    {
        try {
            $this->rpcSecurity->isValidPostRequest();
            $requestId = $this->zendServer->getRequest()->getId();
            if (is_null($requestId) || empty($requestId)) {
                $this->zendServer->getRequest()->setId(uniqid());
            }
            $requestMethod = $this->zendServer->getRequest()->getMethod();
            if (false === $this->zendServer->getServiceMap()->getService($requestMethod)) {
                $this->zendServer->getRequest()->setMethod($this->checkNsAliasFromMethodName($requestMethod));
            }
            $response = $this->zendServer->handle();
        } catch (BadRequestException $e) {
            $response = $this->getServer()->fault($e->getMessage(), Error::ERROR_INVALID_PARAMS, $e);
        } catch (\Exception $e) {
            $response = $this->getServer()->fault($e->getMessage(), Error::ERROR_OTHER, $e);
        }

        return $response;
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
     * @param string $message
     * @param int $code
     * @param mixed $data
     * @return Http
     */
    protected function createErrorResponse($message, $code, $data = null)
    {
        if ($code == Error::ERROR_OTHER && $this->environment != 'dev') {
            $message = 'Everything is bad. Call admin.';
            $data = null;
        }

        $response = new Http();
        $error = new Error($message, $code, $data);
        $response->setError($error);
        return $response;
    }

    /**
     * @return mixed
     */
    public function getServiceMap()
    {
        try {
            $this->rpcSecurity->isValidGetRequest();
            $this->zendServer->setTarget($this->router->generate('ufo_rpc_api_server'))
                ->setEnvelope(\Laminas\Json\Server\Smd::ENV_JSONRPC_2);
            $response = $this->zendServer->getServiceMap();
        } catch (\Exception $e) {
            $response = $this->createErrorResponse($e->getMessage(), Error::ERROR_OTHER, $e);
        }

        return $response;
    }
    
    /**
     * @param string $alias
     * @param string $namespace
     * @return $this
     */
    public function addNsAlias($namespace, $alias)
    {
        $this->nsAliases[$alias] = $namespace;
        return $this;
    }

    /**
     * @return array
     */
    public function getNsAliases()
    {
        return $this->nsAliases;
    }

    /**
     * @param string $alias
     * @return string
     */
    public function checkNsAlias($alias)
    {
        return isset($this->nsAliases[$alias]) ? $this->nsAliases[$alias] : $alias;
    }

    /**
     * @param string $methodName
     * @return string
     */
    protected function checkNsAliasFromMethodName($methodName)
    {
        $n = explode('.', $methodName);
        if (count($n) != 2) {
            return $this->checkNsAlias($methodName);
        }
        $n[0] = $this->checkNsAlias($n[0]);

        return  implode('.', array_diff($n, ['']));
    }
}