<?php

namespace Ufo\JsonRpcBundle\Security;

use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator;

class SimpleTokenValidator implements ITokenValidator
{
    /**
     * SimpleTokenValidator constructor.
     */
    public function __construct(protected RpcMainConfig $rpcConfig) {}

    /**
     * @param string $token
     * @return bool
     * @throws RpcInvalidTokenException
     */
    public function isValid(string $token): true
    {
        if (false === in_array($token, $this->rpcConfig->securityConfig->tokens)) {
            throw new RpcInvalidTokenException();
        }

        return true;
    }

    /**
     * @return array
     */
    public function getClientsTokens(): array
    {
        return $this->rpcConfig->securityConfig->tokens;
    }
}