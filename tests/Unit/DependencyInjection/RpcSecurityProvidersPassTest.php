<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\DependencyInjection;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Ufo\JsonRpcBundle\DependencyInjection\CompilerPass\RpcSecurityProvidersPass;
use Ufo\JsonRpcBundle\Security\TokenValidatorRegistry;
use Ufo\JsonRpcBundle\Tests\Fixtures\GroupTokenValidator;

class RpcSecurityProvidersPassTest extends TestCase
{
    private function container(array $providers): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(TokenValidatorRegistry::class);
        $container->register(GroupTokenValidator::class, GroupTokenValidator::class);
        $container->setParameter('ufo_json_rpc.security.providers', $providers);
        return $container;
    }

    public function testDuplicateGroupProvidersAreRejected(): void
    {
        $provider = ['service' => GroupTokenValidator::class, 'api_groups' => ['bfa']];
        $container = $this->container(['one' => $provider, 'two' => $provider]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiple RPC token providers');
        (new RpcSecurityProvidersPass())->process($container);
    }

    public function testUnknownProviderServiceIsRejected(): void
    {
        $container = $this->container(['one' => ['service' => 'missing', 'api_groups' => ['bfa']]]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown service');
        (new RpcSecurityProvidersPass())->process($container);
    }

    public function testWrongProviderInterfaceIsRejected(): void
    {
        $container = $this->container(['one' => ['service' => 'wrong', 'api_groups' => ['bfa']]]);
        $container->register('wrong', \stdClass::class);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');
        (new RpcSecurityProvidersPass())->process($container);
    }
}
