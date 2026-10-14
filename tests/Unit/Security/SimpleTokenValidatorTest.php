<?php

namespace  Ufo\JsonRpcBundle\Tests\Unit\JsonRpcBundle\Security;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\Security\SimpleTokenValidator;
use Ufo\RpcError\RpcInvalidTokenException;

class SimpleTokenValidatorTest extends TestCase
{
    private SimpleTokenValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SimpleTokenValidator(['valid-token-1', 'valid-token-2']);
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


    public function testSeparateValidatorsUseIndependentTokenLists(): void
    {
        $validator = new SimpleTokenValidator(['group-token', 'rotated-token']);
        self::assertTrue($validator->isValid('group-token'));
        self::assertTrue($validator->isValid('rotated-token'));
        $this->expectException(RpcInvalidTokenException::class);
        $validator->isValid('valid-token-1');
    }

    public function testEmptyTokenListRejectsAllTokens(): void
    {
        $validator = new SimpleTokenValidator([]);
        $this->expectException(RpcInvalidTokenException::class);
        $validator->isValid('valid-token-1');
    }

}
