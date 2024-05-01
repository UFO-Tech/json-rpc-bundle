<?php
/**
 * Created by PhpStorm.
 * User: ashterix
 * Date: 27.09.16
 * Time: 17:42
 */

namespace Ufo\JsonRpcBundle\ApiMethod;


use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;


#[RPC\Info("")]
class PingProcedure implements IRpcService
{
    /**
     * @return string
     */
    public function ping(): string
    {
        return "PONG";
    }

}