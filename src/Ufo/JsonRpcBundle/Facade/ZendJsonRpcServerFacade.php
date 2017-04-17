<?php
/**
 * Created by PhpStorm.
 * User: ashterix
 * Date: 26.09.16
 * Time: 19:04
 */

namespace Ufo\JsonRpcBundle\Facade;


use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\Exceptions\BadRequestException;
use Ufo\JsonRpcBundle\Exceptions\InvalidJsonRpcParamsException;
use Ufo\JsonRpcBundle\Facade\Interfaces\IFacadeJsonRpcServer;
use Zend\Json\Server\Server;
use Zend\Json\Server\Error;
use Zend\Json\Server\Request;
use Zend\Json\Server\Response\Http;

class ZendJsonRpcServerFacade implements IFacadeJsonRpcServer
{
    /**
     * @var Server
     */
    protected $zendServer;

    /**
     * @var string
     */
    protected $environment;

    /**
     * @var array
     */
    protected $nsAliases = [];

    /**
     * ZendJsonRpcServerFacade constructor.
     * @param string $environment
     */
    public function __construct($environment)
    {
        $this->zendServer = new Server();
        $this->environment = $environment;
    }

    /**
     * @return object JsonRpc Server
     */
    public function getServer()
    {
        return $this->zendServer;
    }

    /**
     * @param IRpcService $procedure
     * @param string $namespace
     * @param mixed|null $argv
     * @return $this
     */
    public function addProcedure(IRpcService $procedure, $namespace = '', $argv = null)
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
            $requestId = $this->zendServer->getRequest()->getId();
            if (is_null($requestId) || empty($requestId)) {
                $this->zendServer->getRequest()->setId(uniqid());
            }
            $requestMethod = $this->zendServer->getRequest()->getMethod();
            if (false === $this->zendServer->getServiceMap()->getService($requestMethod)) {
                $this->zendServer->getRequest()->setMethod($this->checkNsAlias($requestMethod));
            }
            $response = $this->zendServer->handle();
        } catch (BadRequestException $e) {
            $response = $this->createErrorResponse($e->getMessage(), Error::ERROR_INVALID_PARAMS, $e);
        } catch (\Exception $e) {
            $response = $this->createErrorResponse($e->getMessage(), Error::ERROR_OTHER, $e);
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
        $this->zendServer->setTarget('/json-rpc.php')
            ->setEnvelope(\Zend\Json\Server\Smd::ENV_JSONRPC_2);

        return $this->zendServer->getServiceMap();
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
}