<?php

namespace  Ufo\JsonRpcBundle\Tests\Unit\JsonRpcBundle\Security;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\ConfigService\RpcSecurityConfig;
use Ufo\JsonRpcBundle\Security\SimpleTokenValidator;
use Ufo\JsonRpcBundle\Tests\Unit\ConfigHolderTrait;
use Ufo\RpcError\RpcInvalidTokenException;

class SimpleTokenValidatorTest extends TestCase
{
    use ConfigHolderTrait;
    private SimpleTokenValidator $validator;

    protected function setUp(): void
    {
        $this->setUpConfig([
            RpcSecurityConfig::NAME => [
                RpcSecurityConfig::PROTECTED_API => true,
                RpcSecurityConfig::TOKENS => ['valid-token-1', 'valid-token-2'],
            ]
        ]);
        $this->validator = new SimpleTokenValidator($this->rpcMainConfig);

    }

    public function testIsValidWithValidToken(): void
    {
        $this->assertTrue($this->validator->isValid('valid-token-1'));
    }

    public function testIsValidWithInvalidToken(): void
    {
        $this->expectException(RpcInvalidTokenException::class);
        $this->validator->isValid('invalid-token');
    }

    public function testIsValidWithAnotherValidToken(): void
    {
        $this->assertTrue($this->validator->isValid('valid-token-2'));
    }

}