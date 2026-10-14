<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures;

use Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator;
use Ufo\RpcError\RpcInvalidTokenException;

class GroupTokenValidator implements ITokenValidator
{
    public function isValid(string $token): true
    {
        if ($token !== 'bfa-token') {
            throw new RpcInvalidTokenException();
        }
        return true;
    }
}
