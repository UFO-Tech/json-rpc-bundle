<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Ufo\JsonRpcBundle\ConfigService\RpcAsyncConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\DependencyInjection\UfoJsonRpcExtension;
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

    public function testPrependMapsAllowedAsyncTransportToFrameworkMessenger(): void
    {
        $extension = new UfoJsonRpcExtension();
        $container = new ContainerBuilder();
        $container->prependExtensionConfig(RpcMainConfig::NAME, [
            RpcAsyncConfig::NAME => [[
                RpcAsyncConfig::K_TYPE => 'amqp',
                RpcAsyncConfig::K_CONFIG => [
                    'name' => 'rpc_async',
                    'dsn' => 'amqp://guest:guest@127.0.0.1:5672/%2f/messages',
                ],
            ]],
        ]);

        $extension->prepend($container);
        $frameworkConfigs = $container->getExtensionConfig('framework');

        $this->assertNotEmpty($frameworkConfigs);
        $messenger = $frameworkConfigs[0]['messenger'];
        $this->assertSame(
            'amqp://guest:guest@127.0.0.1:5672/%2f/messages',
            $messenger['transports']['rpc_async']
        );
        $this->assertSame(
            'rpc_async',
            $messenger['routing'][RpcAsyncRequest::class]
        );
    }

    public function testPrependIgnoresNotAllowedProtocols(): void
    {
        $extension = new UfoJsonRpcExtension();
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
        $extension = new UfoJsonRpcExtension();
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

    public function testAliasAndRouteHelpers(): void
    {
        $extension = new UfoJsonRpcExtension();

        $this->assertSame(RpcMainConfig::NAME, $extension->getAlias());

        $method = new \ReflectionMethod($extension, 'getRoute');
        $method->setAccessible(true);

        $this->assertSame(RpcAsyncRequest::class, $method->invoke($extension, 'amqp'));
        $this->assertSame(RpcAsyncRequest::class, $method->invoke($extension, 'unknown'));
    }

    private function setProtectedProperty(object $object, string $property, mixed $value): void
    {
        $ref = new \ReflectionObject($object);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }
}
