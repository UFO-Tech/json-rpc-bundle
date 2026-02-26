<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\ConfigService;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\ConfigService\RpcAsyncConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcAsyncInfo;
use Ufo\RpcObject\RpcAsyncRequest;

class RpcAsyncInfoTest extends TestCase
{
    public function testFromArrayUsesDefaults(): void
    {
        $info = RpcAsyncInfo::fromArray([
            'config' => [],
        ]);

        $this->assertSame(RpcAsyncConfig::DEFAULT_TYPE, $info->type);
        $this->assertSame(RpcAsyncRequest::class, $info->name);
        $this->assertSame([], $info->config);
    }

    public function testFromArrayUsesProvidedTypeAndNameAndRemovesNameFromConfig(): void
    {
        $info = RpcAsyncInfo::fromArray([
            'type' => 'mercure',
            'config' => [
                'name' => 'rpc_socket',
                'dsn' => 'https://example.test/.well-known/mercure',
                'topics_prefix' => 'rpc.event.',
            ],
        ]);

        $this->assertSame('mercure', $info->type);
        $this->assertSame('rpc_socket', $info->name);
        $this->assertSame([
            'dsn' => 'https://example.test/.well-known/mercure',
            'topics_prefix' => 'rpc.event.',
        ], $info->config);
    }
}

