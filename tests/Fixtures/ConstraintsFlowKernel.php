<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Ufo\JsonRpcBundle\Tests\Fixtures\Procedures\ConstraintsFlowProcedure;

class ConstraintsFlowKernel extends TestKernel
{
    public function getProjectDir(): string
    {
        return __DIR__.'/cli_app';
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/constraints-flow';
    }

    protected function configureContainer(ContainerConfigurator $c): void
    {
        parent::configureContainer($c);
        $c->services()->set(ConstraintsFlowProcedure::class)->autowire()->autoconfigure()->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../../config/router.yaml')->prefix('/rpc');
    }
}
