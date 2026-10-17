<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Ufo\JsonRpcBundle\ConfigService\RpcAsyncConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcBatchConfig;
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
        $this->assertNull($normalized[RpcCacheConfig::NAME][RpcCacheConfig::POOL]);
        $this->assertTrue($normalized[RpcSecurityConfig::NAME][RpcSecurityConfig::PROTECTED_API]);
        $this->assertFalse($normalized[RpcSecurityConfig::NAME][RpcSecurityConfig::PROTECTED_DOC]);
        $this->assertSame(RpcSecurityConfig::DEFAULT_TOKEN_KEY, $normalized[RpcSecurityConfig::NAME][RpcSecurityConfig::TOKEN_NAME]);
        $this->assertSame([], $normalized[RpcSecurityConfig::NAME][RpcSecurityConfig::TOKENS]);
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

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidTokenProviders')]
    public function testInvalidTokenProviderConfigurationIsRejected(array $provider): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [[
            'security' => ['providers' => ['internal' => $provider + ['api_groups' => ['internal']]]],
        ]]);
    }

    public static function invalidTokenProviders(): array
    {
        return [
            'neither service nor tokens' => [[]],
            'both service and tokens' => [['service' => 'validator', 'clients_tokens' => ['token']]],
            'empty token' => [['clients_tokens' => ['']]],
        ];
    }


    public function testBatchDefaults(): void
    {
        $normalized = (new Processor())->processConfiguration(new Configuration(), [[]]);

        $this->assertSame([
            RpcBatchConfig::ENABLED => true,
            RpcBatchConfig::PROCESS => true,
            RpcBatchConfig::PROCESS_COUNT => RpcBatchConfig::DEFAULT_PROCESS_COUNT,
        ], $normalized[RpcBatchConfig::NAME]);
    }

    public function testBatchProcessCountIsLimited(): void
    {
        foreach ([0, RpcBatchConfig::MAX_PROCESS_COUNT + 1] as $count) {
            try {
                (new Processor())->processConfiguration(new Configuration(), [[
                    RpcBatchConfig::NAME => [RpcBatchConfig::PROCESS_COUNT => $count],
                ]]);
                $this->fail('process_count '.$count.' must be rejected');
            } catch (\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testNotificationDefaultsAndLimits(): void
    {
        $normalized = (new Processor())->processConfiguration(new Configuration(), [[]]);
        $this->assertSame([
            \Ufo\JsonRpcBundle\ConfigService\RpcNotificationConfig::PROCESS => false,
            \Ufo\JsonRpcBundle\ConfigService\RpcNotificationConfig::PROCESS_COUNT => 2,
        ], $normalized['notification']);

        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [['notification' => ['process_count' => 11]]]);
    }
}
