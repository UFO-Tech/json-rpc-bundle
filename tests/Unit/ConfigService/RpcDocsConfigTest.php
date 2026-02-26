<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\ConfigService;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\ConfigService\RpcDocsConfig;
use Ufo\JsonRpcBundle\Tests\Unit\ConfigHolderTrait;

class RpcDocsConfigTest extends TestCase
{
    use ConfigHolderTrait;

    protected function setUp(): void
    {
        $this->setConfig();
    }

    public function testUsesDefaultValues(): void
    {
        $config = new RpcDocsConfig([], $this->rpcMainConfig);

        $this->assertSame('methods', $config->keyForMethods);
        $this->assertFalse($config->asyncDsnInfo);
        $this->assertTrue($config->needJsonSchema);
        $this->assertFalse($config->needSymfonyAsserts);
        $this->assertSame('rpc', $config->projectName);
        $this->assertSame('', $config->projectDesc);
        $this->assertNull($config->projectVersion);
    }

    public function testUsesProvidedValues(): void
    {
        $config = new RpcDocsConfig([
            RpcDocsConfig::ASYNC_DSN_INFO => true,
            RpcDocsConfig::VALIDATIONS => [
                RpcDocsConfig::SYMFONY_ASSERTS => true,
            ],
            RpcDocsConfig::P_PROJECT_NAME => 'Bundle API',
            RpcDocsConfig::P_PROJECT_DESC => 'JSON RPC',
            RpcDocsConfig::P_PROJECT_VER => '10.0.0',
        ], $this->rpcMainConfig);

        $this->assertTrue($config->asyncDsnInfo);
        $this->assertTrue($config->needSymfonyAsserts);
        $this->assertSame('Bundle API', $config->projectName);
        $this->assertSame('JSON RPC', $config->projectDesc);
        $this->assertSame('10.0.0', $config->projectVersion);
    }
}
