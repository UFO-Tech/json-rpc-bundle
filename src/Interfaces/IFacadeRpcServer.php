<?php
/**
 * Created by PhpStorm.
 * User: ashterix
 * Date: 26.09.16
 * Time: 18:58
 */

namespace Ufo\JsonRpcBundle\Interfaces;


use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Server\RpcRequestObject;
use Ufo\JsonRpcBundle\Server\RpcResponseObject;
use Ufo\JsonRpcBundle\Server\RpcServer;

interface IFacadeRpcServer
{
    /**
     * @return RpcServer Server
     */
    public function getServer(): RpcServer;

    /**
     * @param IRpcService $procedure
     * @param string $namespace
     * @param mixed|null $argv
     * @return $this
     */
    public function addProcedure(IRpcService $procedure, string $namespace = '', mixed $argv = null): static;

    /**
     * @param IRpcService[] $procedures
     */
    public function setProcedures(iterable $procedures): void;

    /**
     * @return mixed
     */
    public function handle(RpcRequestObject $singleRequest): RpcResponseObject;

    /**
     * @return mixed
     */
    public function getServiceMap();

    public function getSecurity(): IRpcSecurity;
        
}