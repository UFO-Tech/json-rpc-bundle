<?php

namespace Ufo\JsonRpcBundle\Security\Interfaces;


use Ufo\RpcError\RpcInvalidTokenException;

interface ITokenValidator
{
    /**
     * @param string $token
     * @return bool
     * @throws RpcInvalidTokenException
     */
    public function isValid(string $token): true;
}