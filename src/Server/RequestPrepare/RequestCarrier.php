<?php

namespace Ufo\JsonRpcBundle\Server\RequestPrepare;

use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\IRpcSpecialParamHandler;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\SpecialRpcParams;
use Ufo\RpcObject\SpecialRpcParamsEnum;

class RequestCarrier implements IRpcSpecialParamHandler
{
    protected ?IRpcRequestCarrier $carrier = null;
    protected SpecialRpcParams $specialRpcParams;
    protected array $specialParams = [];

    public function setCarrier(IRpcRequestCarrier $carrier): static
    {
        $this->carrier = $carrier;
        try {
            $params = $this->getRequestObject()->getSpecialParams();
        } catch (WrongWayException) {
            $params = [];
        }
        $this->specialRpcParams = SpecialRpcParamsEnum::fromArray($params);
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
        $this->specialRpcParams = SpecialRpcParamsEnum::fromArray($this->specialParams);
        return $this->specialRpcParams->toArray();
    }

    public function getCarrier(): ?IRpcRequestCarrier
    {
        return $this->carrier;
    }

    public function setParam(string $name, mixed $value): static
    {
        $this->specialParams[$name] = $value;
        return $this;
    }

    public function resetParams(): static
    {
        $this->specialParams = [];
        return $this;
    }
}