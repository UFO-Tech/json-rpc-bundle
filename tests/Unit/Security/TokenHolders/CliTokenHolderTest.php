<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\JsonRpcBundle\Security\TokenHolders;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcSecurityConfig;
use Ufo\JsonRpcBundle\Security\TokenHolders\CliTokenHolder;
use Ufo\JsonRpcBundle\Tests\Unit\ConfigHolderTrait;
use Ufo\RpcError\RpcTokenNotSentException;

class CliTokenHolderTest extends TestCase
{
    use ConfigHolderTrait;

    private InputInterface $inputMock;

    private CliTokenHolder $cliTokenHolder;

    protected function setUp(): void
    {
        $this->setUpConfig([
            RpcSecurityConfig::NAME => [
                RpcSecurityConfig::TOKEN_NAME => 'AUTH_TOKEN',
                RpcSecurityConfig::PROTECTED_API => true,
                RpcSecurityConfig::TOKENS => ['test_token'],
            ]
        ]);
        $this->inputMock = $this->createMock(InputInterface::class);
        $this->cliTokenHolder = new CliTokenHolder($this->rpcMainConfig, $this->inputMock);
    }

    public function testGetTokenReturnsTokenWhenOptionExists(): void
    {
        $this->inputMock->expects($this->once())->method('getOption')->with('auth_token')->willReturn('test_token');
        $token = $this->cliTokenHolder->getToken();
        $this->assertEquals('test_token', $token);
    }

    public function testGetTokenThrowsExceptionWhenOptionDoesNotExist(): void
    {
        $this->inputMock->expects($this->once())->method('getOption')->with('auth_token')->willReturn(null);
        $this->expectException(RpcTokenNotSentException::class);
        $this->expectExceptionMessage('Token not set!'.PHP_EOL.'This protected command, use option --auth_token');
        $this->cliTokenHolder->getToken();
    }

}