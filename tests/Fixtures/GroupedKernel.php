<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Ufo\JsonRpcBundle\Tests\Fixtures\Procedures\GroupedProcedure;
use Ufo\JsonRpcBundle\Tests\Fixtures\Procedures\GroupedV2Procedure;

class GroupedKernel extends TestKernel
{
    public function getProjectDir(): string
    {
        return __DIR__.'/cli_app';
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/groups';
    }

    protected function configureContainer(ContainerConfigurator $c): void
    {
        parent::configureContainer($c);
        $c->services()->set(GroupedProcedure::class)->autowire()->autoconfigure()->public();
        $c->services()->set(GroupedV2Procedure::class)->autowire()->autoconfigure()->public();
        $c->services()->set(\Ufo\JsonRpcBundle\Tests\Fixtures\Procedures\ToolsProcedure::class)->autowire()->autoconfigure()->public();
        $c->services()->set(GroupTokenValidator::class)->autowire()->autoconfigure()->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/group_routes.yaml');
    }
}
