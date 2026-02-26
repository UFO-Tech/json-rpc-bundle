<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\ConfigService;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\ConfigService\RpcSecurityConfig;
use Ufo\JsonRpcBundle\Tests\Unit\ConfigHolderTrait;

class RpcSecurityConfigTest extends TestCase
{
    use ConfigHolderTrait;

    protected function setUp(): void
    {
        $this->setConfig();
    }

    public function testUsesDefaultValues(): void
    {
        $config = new RpcSecurityConfig([], $this->rpcMainConfig);

        $this->assertFalse($config->protectedApi);
        $this->assertFalse($config->protectedDoc);
        $this->assertSame(RpcSecurityConfig::DEFAULT_TOKEN_KEY, $config->tokenName);
        $this->assertSame([], $config->tokens);
    }

    public function testUsesProvidedValues(): void
    {
        $config = new RpcSecurityConfig([
            RpcSecurityConfig::PROTECTED_API => true,
            RpcSecurityConfig::PROTECTED_DOC => true,
            RpcSecurityConfig::TOKEN_NAME => 'X-Token',
            RpcSecurityConfig::TOKENS => ['a', 'b'],
        ], $this->rpcMainConfig);

        $this->assertTrue($config->protectedApi);
        $this->assertTrue($config->protectedDoc);
        $this->assertSame('X-Token', $config->tokenName);
        $this->assertSame(['a', 'b'], $config->tokens);
    }
}

