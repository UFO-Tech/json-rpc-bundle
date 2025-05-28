<?php

namespace Ufo\JsonRpcBundle\Server\RequestPrepare;

use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcRequest;

interface IRpcRequestCarrier
{
    /**
     * @throws WrongWayException
     * @return RpcRequest
     */
    public function getRequestObject(): RpcRequest;

    /**
     * @throws WrongWayException
     * @return RpcBatchRequest
     */
    public function getBatchRequestObject(): RpcBatchRequest;

}