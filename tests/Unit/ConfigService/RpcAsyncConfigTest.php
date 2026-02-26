<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\ConfigService;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\ConfigService\RpcAsyncConfig;
use Ufo\JsonRpcBundle\Tests\Unit\ConfigHolderTrait;
use Ufo\RpcError\RpcAsyncRequestException;

class RpcAsyncConfigTest extends TestCase
{
    use ConfigHolderTrait;

    protected function setUp(): void
    {
        $this->setConfig();
    }

    public function testConstructorBuildsAsyncMapAndHandlesDuplicateTypes(): void
    {
        $config = new RpcAsyncConfig([
            [
                'type' => 'amqp',
                'config' => [
                    'name' => 'primary_async',
                    'dsn' => 'amqp://guest:guest@127.0.0.1:5672/%2f/messages',
                ],
            ],
            [
                'type' => 'amqp',
                'config' => [
                    'name' => 'secondary_async',
                    'dsn' => 'amqp://guest:guest@127.0.0.1:5672/%2f/messages_2',
                ],
            ],
        ], $this->rpcMainConfig);

        $this->assertArrayHasKey('amqp', $config->rpcAsync);
        $this->assertArrayHasKey('amqp_2', $config->rpcAsync);
        $this->assertSame('primary_async', $config->rpcAsync['amqp']->name);
        $this->assertSame('secondary_async', $config->rpcAsync['amqp_2']->name);
    }

    public function testGetConfigReturnsConfiguredAsyncInfo(): void
    {
        $config = new RpcAsyncConfig([
            [
                'type' => 'mercure',
                'config' => [
                    'name' => 'rpc_socket',
                    'dsn' => 'https://example.test/.well-known/mercure',
                ],
            ],
        ], $this->rpcMainConfig);

        $this->assertSame('rpc_socket', $config->getConfig('mercure')->name);
    }

    public function testGetConfigThrowsWhenTypeIsMissing(): void
    {
        $config = new RpcAsyncConfig([], $this->rpcMainConfig);

        $this->expectException(RpcAsyncRequestException::class);
        $config->getConfig('not-configured');
    }
}

