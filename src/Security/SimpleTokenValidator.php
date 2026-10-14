<?php

namespace Ufo\JsonRpcBundle\Security;

use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator;

class SimpleTokenValidator implements ITokenValidator
{
    public function __construct(protected array $tokens = []) {}

    /**
     * @param string $token
     * @return bool
     * @throws RpcInvalidTokenException
     */
    public function isValid(string $token): true
    {
        if (false === in_array($token, $this->tokens)) {
            throw new RpcInvalidTokenException();
        }
        return true;
    }
}
