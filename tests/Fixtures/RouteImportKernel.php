<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class RouteImportKernel extends TestKernel
{
    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/route_import';
    }

    protected function configureContainer(ContainerConfigurator $c): void
    {
        parent::configureContainer($c);
        $c->services()->set(AdapterRouteImport::class)->autowire()->autoconfigure();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/route_import/routes.yaml');
    }
}
