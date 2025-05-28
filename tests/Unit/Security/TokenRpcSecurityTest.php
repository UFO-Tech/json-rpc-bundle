<?php

namespace  Ufo\JsonRpcBundle\Tests\Unit\JsonRpcBundle\Security;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\ConfigService\RpcSecurityConfig;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcTokenHolder;
use Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator;
use Ufo\JsonRpcBundle\Security\TokenRpcSecurity;
use Ufo\JsonRpcBundle\Tests\Unit\ConfigHolderTrait;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcTokenNotSentException;

class TokenRpcSecurityTest extends TestCase
{
    use ConfigHolderTrait;

    private ITokenValidator $tokenValidatorMock;

    private IRpcTokenHolder $tokenHolderMock;

    private TokenRpcSecurity $tokenRpcSecurity;

    protected function setUp(): void
    {
        $this->tokenValidatorMock = $this->createMock(ITokenValidator::class);
        $this->tokenHolderMock = $this->createMock(IRpcTokenHolder::class);
        $this->setUpConfig([
            RpcSecurityConfig::NAME => [
                RpcSecurityConfig::PROTECTED_API => true,
            ]
        ]);
    }

    protected function setUpConfig(array $config = []): void
    {
        $this->setConfig($config);
        $this->tokenRpcSecurity = new TokenRpcSecurity($this->rpcMainConfig, $this->tokenValidatorMock);
        $this->tokenRpcSecurity->setTokenHolder($this->tokenHolderMock);
    }

    public function testIsValidApiRequestWhenNotProtectedApi(): void
    {
        $this->setUpConfig([
            RpcSecurityConfig::NAME => [
                RpcSecurityConfig::PROTECTED_API => false,
            ]
        ]);

        $this->tokenValidatorMock->expects($this->never())->method('isValid');
        $this->assertTrue($this->tokenRpcSecurity->isValidApiRequest());
    }

    public function testIsValidApiRequestWhenTokenIsValid(): void
    {
        $this->tokenHolderMock->expects($this->once())->method('getToken')->willReturn('valid-token');
        $this->tokenValidatorMock->expects($this->once())->method('isValid')->with('valid-token');
        $this->assertTrue($this->tokenRpcSecurity->isValidApiRequest());
    }

    public function testIsValidApiRequestWhenTokenNotSent(): void
    {
        $this->tokenHolderMock->expects($this->once())
                              ->method('getToken')
                              ->willThrowException(new RpcTokenNotSentException())
        ;
        $this->expectException(RpcTokenNotSentException::class);
        $this->tokenRpcSecurity->isValidApiRequest();
    }

    public function testIsValidApiRequestWhenTokenIsInvalid(): void
    {
        $this->tokenHolderMock->expects($this->once())->method('getToken')->willReturn('invalid-token');
        $this->tokenValidatorMock->expects($this->once())
                                 ->method('isValid')
                                 ->with('invalid-token')
                                 ->willThrowException(new RpcInvalidTokenException())
        ;
        $this->expectException(RpcInvalidTokenException::class);
        $this->tokenRpcSecurity->isValidApiRequest();
    }

    public function testGetTokenHolderReturnsInstanceWhenSet(): void
    {
        $this->assertSame($this->tokenHolderMock, $this->tokenRpcSecurity->getTokenHolder());
    }

    public function testGetTokenHolderThrowsExceptionWhenNotSet(): void
    {
        $this->tokenRpcSecurity = new TokenRpcSecurity($this->rpcMainConfig, $this->tokenValidatorMock);
        $this->expectException(RpcTokenNotSentException::class);
        $this->tokenRpcSecurity->getTokenHolder();
    }

}