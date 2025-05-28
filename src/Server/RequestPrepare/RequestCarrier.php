<?php

namespace Ufo\JsonRpcBundle\Server\RequestPrepare;

use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\IRpcSpecialParamHandler;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcRequest;

class RequestCarrier implements IRpcSpecialParamHandler
{
    protected ?IRpcRequestCarrier $carrier = null;

    public function __construct() {}

    public function setCarrier(IRpcRequestCarrier $carrier): static
    {
        $this->carrier = $carrier;
        return $this;
    }

    /**
     * @throws WrongWayException
     */
    public function getRequestObject(): RpcRequest
    {
        return $this->carrier?->getRequestObject() ?? throw new WrongWayException();
    }

    /**
     * @throws WrongWayException
     */
    public function getBatchRequestObject(): RpcBatchRequest
    {
        return $this->carrier?->getBatchRequestObject() ?? throw new WrongWayException();
    }

    public function getSpecialParams(): array
    {
        try {
            $params = $this->getRequestObject()->getParams();
        } catch (WrongWayException) {
            $params = [];
        }
        return $params;
    }

}