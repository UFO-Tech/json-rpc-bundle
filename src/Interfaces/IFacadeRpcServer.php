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
use Ufo\JsonRpcBundle\Server\RpcServer;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

interface IFacadeRpcServer
{
    /**
     * @return RpcServer Server
     */
    public function getServer(): RpcServer;

    /**
     * @param IRpcService $procedure
     * @param string $namespace
     * @return $this
     */
    public function addProcedure(IRpcService $procedure, string $namespace = ''): static;

    /**
     * @param IRpcService[] $procedures
     */
    public function setProcedures(iterable $procedures): void;

    /**
     * @return mixed
     */
    public function handle(RpcRequest $singleRequest): RpcResponse;

    /**
     * @return mixed
     */
    public function getServiceMap(): mixed;

    public function getSecurity(): IRpcSecurity;
        
}