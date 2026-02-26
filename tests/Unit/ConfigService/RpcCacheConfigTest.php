<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\ConfigService;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\ConfigService\RpcCacheConfig;
use Ufo\JsonRpcBundle\Tests\Unit\ConfigHolderTrait;
use Ufo\RpcObject\RPC\Cache;

class RpcCacheConfigTest extends TestCase
{
    use ConfigHolderTrait;

    protected function setUp(): void
    {
        $this->setConfig();
    }

    public function testUsesDefaultValues(): void
    {
        $config = new RpcCacheConfig([], $this->rpcMainConfig);

        $this->assertSame(Cache::T_MINUTE, $config->ttl);
        $this->assertSame(RpcCacheConfig::P_PREFIX, $config->prefix);
    }

    public function testUsesProvidedValues(): void
    {
        $config = new RpcCacheConfig([
            RpcCacheConfig::TTL => 120,
            RpcCacheConfig::PREFIX => 'custom_prefix',
        ], $this->rpcMainConfig);

        $this->assertSame(120, $config->ttl);
        $this->assertSame('custom_prefix', $config->prefix);
    }
}

