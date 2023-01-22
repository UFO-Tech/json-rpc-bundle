<?php
/**
 * Created by PhpStorm.
 * User: ashterix
 * Date: 26.09.16
 * Time: 18:58
 */

namespace Ufo\JsonRpcBundle\Facade\Interfaces;


use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\Server\UfoZendServer;

interface IFacadeJsonRpcServer
{
    /**
     * @return UfoZendServer Server
     */
    public function getServer(): UfoZendServer;

    /**
     * @param IRpcService $procedure
     * @param string $namespace
     * @param mixed|null $argv
     * @return $this
     */
    public function addProcedure(IRpcService $procedure, string $namespace = '', mixed $argv = null): static;

    /**
     * @return mixed
     */
    public function handle();

    /**
     * @return mixed
     */
    public function getServiceMap();
        
}