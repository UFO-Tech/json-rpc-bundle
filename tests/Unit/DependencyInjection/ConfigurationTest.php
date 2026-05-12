<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Ufo\JsonRpcBundle\ConfigService\RpcAsyncConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcCacheConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcDocsConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcSecurityConfig;
use Ufo\JsonRpcBundle\DependencyInjection\Configuration;
use Ufo\RpcObject\RPC\Cache;

class ConfigurationTest extends TestCase
{
    public function testTreeBuilderNameAndDefaultValues(): void
    {
        $configuration = new Configuration();
        $normalized = (new Processor())->processConfiguration($configuration, [[
            RpcCacheConfig::NAME => [],
            RpcSecurityConfig::NAME => [],
            RpcDocsConfig::NAME => [],
        ]]);

        $this->assertSame(RpcMainConfig::NAME, Configuration::TREE_BUILDER_NAME);
        $this->assertSame(Cache::T_MINUTE, $normalized[RpcCacheConfig::NAME][RpcCacheConfig::TTL]);
        $this->assertSame(RpcCacheConfig::P_PREFIX, $normalized[RpcCacheConfig::NAME][RpcCacheConfig::PREFIX]);
        $this->assertTrue($normalized[RpcSecurityConfig::NAME][RpcSecurityConfig::PROTECTED_API]);
        $this->assertFalse($normalized[RpcSecurityConfig::NAME][RpcSecurityConfig::PROTECTED_DOC]);
        $this->assertSame(RpcSecurityConfig::DEFAULT_TOKEN_KEY, $normalized[RpcSecurityConfig::NAME][RpcSecurityConfig::TOKEN_NAME]);
        $this->assertSame(RpcDocsConfig::P_PROJECT_NAME_DEFAULT, $normalized[RpcDocsConfig::NAME][RpcDocsConfig::P_PROJECT_NAME]);
    }

    public function testAsyncStringConfigIsNormalizedToSingleTransport(): void
    {
        $configuration = new Configuration();
        $normalized = (new Processor())->processConfiguration($configuration, [[
            RpcAsyncConfig::NAME => 'amqp://guest:guest@127.0.0.1:5672/%2f/messages',
        ]]);

        $this->assertSame([[
            RpcAsyncConfig::K_TYPE => RpcAsyncConfig::DEFAULT_TYPE,
            RpcAsyncConfig::K_CONFIG => [
                // Current config normalization applies both "ifString" and "ifArray" branches.
                'name' => 'type',
                'dsn' => RpcAsyncConfig::DEFAULT_TYPE,
            ],
        ]], $normalized[RpcAsyncConfig::NAME]);
    }

    public function testAsyncAssocArrayConfigIsNormalizedToNamedTransport(): void
    {
        $configuration = new Configuration();
        $normalized = (new Processor())->processConfiguration($configuration, [[
            RpcAsyncConfig::NAME => [
                'custom_async' => 'amqp://guest:guest@127.0.0.1:5672/%2f/messages',
            ],
        ]]);

        $this->assertSame([[
            RpcAsyncConfig::K_TYPE => RpcAsyncConfig::DEFAULT_TYPE,
            RpcAsyncConfig::K_CONFIG => [
                'name' => 'custom_async',
                'dsn' => 'amqp://guest:guest@127.0.0.1:5672/%2f/messages',
            ],
        ]], $normalized[RpcAsyncConfig::NAME]);
    }
}
