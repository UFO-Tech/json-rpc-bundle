<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\JsonRpcBundle\Security\TokenHolders;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\ConfigService\RpcSecurityConfig;
use Ufo\JsonRpcBundle\Security\TokenHolders\HttpTokenHolder;
use Ufo\JsonRpcBundle\Tests\Unit\ConfigHolderTrait;
use Ufo\RpcError\RpcTokenNotSentException;

class HttpTokenHolderTest extends TestCase
{
    use ConfigHolderTrait;

    public function testGetTokenSuccessfullyRetrievesTokenFromHeaders(): void
    {
        $tokenKey = 'Authorization-Token';
        $tokenValue = 'sample_token_value';
        $this->setUpConfig([
            RpcSecurityConfig::NAME => [
                RpcSecurityConfig::PROTECTED_API => true,
                RpcSecurityConfig::TOKEN_NAME => $tokenKey,
                RpcSecurityConfig::TOKENS => [$tokenValue],
            ]
        ]);
        $request = new Request();
        $request->headers->set($tokenKey, $tokenValue);
        $tokenHolder = new HttpTokenHolder($this->rpcMainConfig, $request);
        $this->assertSame($tokenValue, $tokenHolder->getToken());
    }

    public function testGetTokenThrowsExceptionWhenTokenIsNotSent(): void
    {
        $this->expectException(RpcTokenNotSentException::class);
        $tokenKey = 'Authorization-Token';
        $tokenValue = 'sample_token_value';
        $this->setUpConfig([
            RpcSecurityConfig::NAME => [
                RpcSecurityConfig::PROTECTED_API => true,
                RpcSecurityConfig::TOKEN_NAME => $tokenKey,
                RpcSecurityConfig::TOKENS => [$tokenValue],
            ]
        ]);
        $request = new Request();
        $tokenHolder = new HttpTokenHolder($this->rpcMainConfig, $request);
        $tokenHolder->getToken();
    }

}