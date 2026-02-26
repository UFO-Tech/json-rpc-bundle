<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\ConfigService;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;

class RpcMainConfigTest extends TestCase
{
    public function testLoadsDefaultsFromInstallConfig(): void
    {
        $config = new RpcMainConfig([], 'test');

        $this->assertTrue($config->securityConfig->protectedApi);
        $this->assertFalse($config->securityConfig->protectedDoc);
        $this->assertSame('My Project', $config->docsConfig->projectName);
        $this->assertSame([], $config->asyncConfig->rpcAsync);
        $this->assertSame(["path"=>''], $config->url);
    }

    public function testParsesRequestUrlAndSdkVendors(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('https://api.example.com:8443/rpc'));

        $config = new RpcMainConfig(
            rpcConfigs: [],
            environment: 'test',
            requestStack: $stack,
            sdkConfigs: [
                'vendors' => [
                    ['name' => 'acme'],
                    ['name' => 'ufo'],
                ],
            ]
        );

        $this->assertSame('https', $config->url['scheme']);
        $this->assertSame('api.example.com', $config->url['host']);
        $this->assertSame(8443, $config->url['port']);
        $this->assertSame('/rpc', $config->url['path']);
        $this->assertSame(['acme', 'ufo'], $config->sdkVendors);
    }

    public function testMergesNestedConfigWithoutLosingDefaults(): void
    {
        $config = new RpcMainConfig([
            'security' => [
                'protected_api' => false,
            ],
            'docs' => [
                'project_name' => 'Custom RPC',
            ],
        ], 'test');

        $this->assertFalse($config->securityConfig->protectedApi);
        $this->assertFalse($config->securityConfig->protectedDoc);
        $this->assertSame('Custom RPC', $config->docsConfig->projectName);
        $this->assertSame('', $config->docsConfig->projectDesc);
    }
}

