<?php

namespace Ufo\JsonRpcBundle\Server;

use Ufo\RpcObject\IRpcSpecialParamHandler;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\SpecialRpcParamsEnum;

class CurrentRpcRequestHolder implements IRpcSpecialParamHandler
{
    protected ?RpcRequest $rpcRequest = null;

    public function setRpcRequest(RpcRequest $rpcRequest): static
    {
        $this->rpcRequest = $rpcRequest;
        return $this;
    }

    public function getRpcRequest(): ?RpcRequest
    {
        return $this->rpcRequest;
    }

    public function getSpecialParams(): array
    {
        $sp = $this->rpcRequest?->getSpecialParams();
        $sp[SpecialRpcParamsEnum::PARENT_REQUEST->value] = $this->rpcRequest?->getId();
        return $sp;
    }
}