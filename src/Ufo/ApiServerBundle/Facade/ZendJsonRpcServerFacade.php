<?php
/**
 * Created by PhpStorm.
 * User: ashterix
 * Date: 26.09.16
 * Time: 19:04
 */

namespace Ufo\JsonRpcBundle\Facade;


use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IApiProcedure;
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
     * ZendJsonRpcServerFacade constructor.
     */
    public function __construct()
    {
        $this->zendServer = new Server();
    }

    /**
     * @return object JsonRpc Server
     */
    public function getServer()
    {
        return $this->zendServer;
    }

    /**
     * @param IApiProcedure $procedure
     * @param string $namespace
     * @param mixed|null $argv
     * @return $this
     */
    public function addProcedure(IApiProcedure $procedure, $namespace = '', $argv = null)
    {
        if (empty($namespace) && is_object($procedure)) {
            $className = explode('\\', get_class($procedure));
            $namespace = array_pop($className);
            if ($namespace == 'Ping') {
                $namespace = '';
            }
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
            if ($this->zendServer->getRequest()) {
                $this->zendServer->getRequest()->setId(uniqid());
            }
            $response = $this->zendServer->handle();
        } catch (InvalidJsonRpcParamsException $e) {
            $response = new Http();
            $error = new Error($e->getMessage(), Error::ERROR_INVALID_PARAMS);
            $response->setError($error);
        }

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
}