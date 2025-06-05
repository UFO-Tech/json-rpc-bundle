<?php

namespace Ufo\JsonRpcBundle\Security\TokenHolders;

use Ufo\JsonRpcBundle\Security\Interfaces\IRpcTokenHolder;
use Ufo\RpcObject\RpcAsyncRequest;

class RpcAsyncTokenHolder implements IRpcTokenHolder
{
    const string TOKEN_NAME = 'token';

    public function __construct(protected RpcAsyncRequest $rpcAsyncRequest) {}

    public function getTokenKey(): string
    {
        return static::TOKEN_NAME;
    }

    public function getToken(): string
    {
        return $this->rpcAsyncRequest->{$this->getTokenKey()} ?? '';
    }

}