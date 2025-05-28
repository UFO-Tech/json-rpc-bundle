<?php

namespace Ufo\JsonRpcBundle\Security\TokenHolders;

use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcTokenHolder;
use Ufo\RpcError\RpcTokenNotSentException;

use function is_null;

class HttpTokenHolder implements IRpcTokenHolder
{
    public function __construct(
        protected RpcMainConfig $rpcConfig,
        protected Request $request,
    ) {}

    public function setRequest(Request $request): static
    {
        $this->request = $request;
        return $this;
    }

    public function getTokenKey(): string
    {
       return $this->rpcConfig->securityConfig->tokenName;
    }

    public function getToken(): string
    {
        if (is_null($token = $this->request->headers->get($this->getTokenKey()))) {
            throw new RpcTokenNotSentException();
        }
        return $token;
    }

}