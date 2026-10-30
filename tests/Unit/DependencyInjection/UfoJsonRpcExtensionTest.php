<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Ufo\Component\TransportContracts\AsyncTransportConfigBuilderInterface;
use Ufo\Component\TransportContracts\AsyncTransportConfigDTO;
use Ufo\JsonRpcBundle\ConfigService\RpcAsyncConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcCacheConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\DependencyInjection\UfoJsonRpcExtension;
use Ufo\JsonRpcBundle\Tests\Fixtures\Procedures\GroupedProcedure;
use Ufo\JsonRpcBundle\Tests\Fixtures\Procedures\GroupedV2Procedure;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcObject\RpcAsyncRequest;

class UfoJsonRpcExtensionTest extends TestCase
{
    public function testLoadSetsMainParameterAndTreeParameters(): void
    {
        $extension = new UfoJsonRpcExtension();
        $container = new ContainerBuilder();

        $extension->load([[
            'security' => [
                'protected_api' => false,
            ],
        ]], $container);

        $this->assertTrue($container->hasParameter(RpcMainConfig::NAME));
        $this->assertFalse($container->getParameter(RpcMainConfig::NAME . '.security.protected_api'));
        $this->assertTrue($container->hasDefinition('Ufo\\JsonRpcBundle\\ConfigService\\RpcMainConfig'));
    }

    public function testDefaultCachePoolIsKeptWhenPoolIsNotConfigured(): void
    {
        $container = new ContainerBuilder();
        (new UfoJsonRpcExtension())->load([[]], $container);

        $this->assertTrue($container->hasDefinition(RpcCacheConfig::POOL_SERVICE));
        $this->assertFalse($container->hasAlias(RpcCacheConfig::POOL_SERVICE));
    }

    public function testConfiguredCachePoolReplacesDefaultPool(): void
    {
        foreach (['cache.app', '@cache.app'] as $pool) {
            $container = new ContainerBuilder();
            (new UfoJsonRpcExtension())->load([[RpcCacheConfig::NAME => [RpcCacheConfig::POOL => $pool]]], $container);

            $this->assertFalse($container->hasDefinition(RpcCacheConfig::POOL_SERVICE));
            $this->assertSame('cache.app', (string)$container->getAlias(RpcCacheConfig::POOL_SERVICE));
        }
    }

    public function testPrependRegistersTransportForEveryGroupAndVersion(): void
    {
        $container = $this->containerWithProcedures();
        $container->prependExtensionConfig(RpcMainConfig::NAME, [
            RpcAsyncConfig::NAME => [$this->asyncEntry('amqp', 'rpc_async')],
        ]);

        $this->extension()->prepend($container);
        $messenger = $container->getExtensionConfig('framework')[0]['messenger'];

        $this->assertEqualsCanonicalizing(
            ['rpc_async.general.v1', 'rpc_async.bfa.v1', 'rpc_async.bff.v1', 'rpc_async.bfa.v2'],
            array_keys($messenger['transports'])
        );
        $this->assertSame([
            'dsn' => '%env(resolve:RPC_ASYNC_DSN)%',
            'options' => ['queue' => 'rpc-async.bfa.v2'],
        ], $messenger['transports']['rpc_async.bfa.v2']);
        $this->assertSame([], $messenger['routing']);
    }

    public function testPlaceholdersInNameReplaceTheSuffix(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig(RpcMainConfig::NAME, [
            RpcAsyncConfig::NAME => [$this->asyncEntry('amqp', 'rpc_{version}_{group}')],
        ]);

        $this->extension()->prepend($container);

        $this->assertSame(
            ['rpc_v1_general'],
            array_keys($container->getExtensionConfig('framework')[0]['messenger']['transports'])
        );
    }

    public function testExtraConfigKeysBecomeTransportOptions(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig(RpcMainConfig::NAME, [
            RpcAsyncConfig::NAME => [$this->asyncEntry('amqp', 'rpc_async', ['auto_setup' => false])],
        ]);

        $this->extension()->prepend($container);
        $transport = $container->getExtensionConfig('framework')[0]['messenger']['transports']['rpc_async.general.v1'];

        $this->assertSame(['queue' => 'rpc-async.general.v1', 'auto_setup' => false], $transport['options']);
    }

    public function testRouteGoesToEveryTransportOfTheEntry(): void
    {
        $container = $this->containerWithProcedures();
        $container->prependExtensionConfig(RpcMainConfig::NAME, [
            RpcAsyncConfig::NAME => [$this->asyncEntry('amqp', 'rpc_async', ['route' => RpcAsyncRequest::class])],
        ]);

        $this->extension()->prepend($container);
        $messenger = $container->getExtensionConfig('framework')[0]['messenger'];

        $this->assertEqualsCanonicalizing(array_keys($messenger['transports']), $messenger['routing'][RpcAsyncRequest::class]);
        $this->assertArrayNotHasKey('route', $messenger['transports']['rpc_async.general.v1']['options']);
    }

    public function testTransportsSharingAQueueAreRefused(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig(RpcMainConfig::NAME, [
            RpcAsyncConfig::NAME => [$this->asyncEntry('amqp', 'rpc_async'), $this->asyncEntry('amqp', 'rpc-async')],
        ]);

        $this->expectException(RpcBadParamException::class);
        $this->expectExceptionMessage('rpc-async.general.v1');

        $this->extension()->prepend($container);
    }

    public function testTransportWithoutDsnIsRefused(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig(RpcMainConfig::NAME, [
            RpcAsyncConfig::NAME => [[RpcAsyncConfig::K_TYPE => 'amqp', RpcAsyncConfig::K_CONFIG => ['name' => 'rpc_async']]],
        ]);

        $this->expectException(RpcBadParamException::class);
        $this->expectExceptionMessage('"rpc_async" has no dsn');

        $this->extension()->prepend($container);
    }

    public function testPrependIgnoresTypesWithoutBuilder(): void
    {
        $extension = $this->extension();
        $container = new ContainerBuilder();
        $container->prependExtensionConfig(RpcMainConfig::NAME, [
            RpcAsyncConfig::NAME => [[
                RpcAsyncConfig::K_TYPE => 'mercure',
                RpcAsyncConfig::K_CONFIG => [
                    'name' => 'rpc_socket',
                    'dsn' => 'https://example.com/.well-known/mercure',
                ],
            ]],
        ]);

        $extension->prepend($container);

        $this->assertSame([], $container->getExtensionConfig('framework'));
    }

    public function testMapAsyncTransportParamsThrowsWhenTypeIsMissing(): void
    {
        $extension = $this->extension();
        $container = new ContainerBuilder();
        $this->setProtectedProperty($extension, 'container', $container);

        $method = new \ReflectionMethod($extension, 'mapAsyncTransportParams');
        $method->setAccessible(true);

        $this->expectException(RpcBadParamException::class);
        $method->invoke($extension, [[
            RpcAsyncConfig::K_CONFIG => [
                'name' => 'rpc_async',
                'dsn' => 'amqp://guest:guest@127.0.0.1:5672/%2f/messages',
            ],
        ]]);
    }

    public function testAlias(): void
    {
        $this->assertSame(RpcMainConfig::NAME, (new UfoJsonRpcExtension())->getAlias());
    }

    private function extension(): UfoJsonRpcExtension
    {
        return new class extends UfoJsonRpcExtension {
            protected function getAsyncConfigBuilders(): array
            {
                return ['amqp' => new class implements AsyncTransportConfigBuilderInterface {
                    public function getType(): string
                    {
                        return 'amqp';
                    }

                    public function getQueueName(string $transportName): string
                    {
                        return str_replace('_', '-', $transportName);
                    }

                    public function build(AsyncTransportConfigDTO $config): AsyncTransportConfigDTO
                    {
                        return new AsyncTransportConfigDTO($config->name, $config->dsn, ['queue' => $this->getQueueName($config->name)] + $config->options);
                    }
                }];
            }
        };
    }

    private function containerWithProcedures(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(GroupedProcedure::class, GroupedProcedure::class);
        $container->register(GroupedV2Procedure::class, GroupedV2Procedure::class);

        return $container;
    }

    private function asyncEntry(string $type, string $name, array $extra = []): array
    {
        return [
            RpcAsyncConfig::K_TYPE => $type,
            RpcAsyncConfig::K_CONFIG => ['name' => $name, 'dsn' => '%env(resolve:RPC_ASYNC_DSN)%', ...$extra],
        ];
    }

    private function setProtectedProperty(object $object, string $property, mixed $value): void
    {
        $ref = new \ReflectionObject($object);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }
}
