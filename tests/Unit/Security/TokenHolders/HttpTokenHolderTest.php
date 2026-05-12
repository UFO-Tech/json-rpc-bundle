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

    public function testGetTokenKeyReturnsConfiguredHeaderName(): void
    {
        $tokenKey = 'Authorization-Token';
        $this->setConfig([
            RpcSecurityConfig::NAME => [
                RpcSecurityConfig::TOKEN_NAME => $tokenKey,
            ]
        ]);

        $tokenHolder = new HttpTokenHolder($this->rpcMainConfig, new Request());

        $this->assertSame($tokenKey, $tokenHolder->getTokenKey());
    }

    public function testGetTokenSuccessfullyRetrievesTokenFromHeaders(): void
    {
        $tokenKey = 'Authorization-Token';
        $tokenValue = 'sample_token_value';
        $this->setConfig([
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
        $this->setConfig([
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

    public function testSetRequestChangesSourceRequest(): void
    {
        $tokenKey = 'Authorization-Token';
        $this->setConfig([
            RpcSecurityConfig::NAME => [
                RpcSecurityConfig::TOKEN_NAME => $tokenKey,
            ]
        ]);

        $first = new Request();
        $second = new Request();
        $second->headers->set($tokenKey, 'new_token');

        $tokenHolder = new HttpTokenHolder($this->rpcMainConfig, $first);
        $tokenHolder->setRequest($second);

        $this->assertSame('new_token', $tokenHolder->getToken());
    }

}
