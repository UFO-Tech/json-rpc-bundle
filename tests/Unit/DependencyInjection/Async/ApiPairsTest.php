<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\DependencyInjection\Async;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Ufo\JsonRpcBundle\DependencyInjection\Async\ApiPairs;
use Ufo\JsonRpcBundle\Tests\Fixtures\Procedures\GroupedProcedure;
use Ufo\JsonRpcBundle\Tests\Fixtures\Procedures\GroupedV2Procedure;

class ApiPairsTest extends TestCase
{
    public function testMethodGroupsReplaceClassGroupsAndIgnoredMethodsAreSkipped(): void
    {
        $this->assertEqualsCanonicalizing(
            [['bfa', 'v1'], ['bff', 'v1'], ['general', 'v1']],
            ApiPairs::ofClass(new ReflectionClass(GroupedProcedure::class))
        );
    }

    public function testVersionComesFromInfo(): void
    {
        $this->assertSame([['bfa', 'v2']], ApiPairs::ofClass(new ReflectionClass(GroupedV2Procedure::class)));
    }

    public function testContainerPairsAlwaysHaveGeneralV1(): void
    {
        $container = new ContainerBuilder();
        $container->register(GroupedV2Procedure::class, GroupedV2Procedure::class);
        $container->register('not_rpc', \stdClass::class);
        $container->register('abstract_rpc', GroupedProcedure::class)->setAbstract(true);

        $this->assertSame(
            [['general', 'v1'], ['bfa', 'v2']],
            ApiPairs::fromContainer($container)
        );
    }

    public function testPairsOfSeveralServicesAreUnique(): void
    {
        $container = new ContainerBuilder();
        $container->register(GroupedProcedure::class, GroupedProcedure::class);
        $container->register(GroupedV2Procedure::class, GroupedV2Procedure::class);

        $this->assertSame(
            [['general', 'v1'], ['bfa', 'v1'], ['bff', 'v1'], ['bfa', 'v2']],
            ApiPairs::fromContainer($container)
        );
    }

    public function testClassFromParameterIsResolved(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('procedure.class', GroupedV2Procedure::class);
        $container->register('procedure', '%procedure.class%');

        $this->assertSame([['general', 'v1'], ['bfa', 'v2']], ApiPairs::fromContainer($container));
    }
}
