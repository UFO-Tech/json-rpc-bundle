<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Ufo\JsonRpcBundle\Tests\Fixtures\Procedures\NamesakeDocProcedure;

/** Ядро з процедурою тезок, щоб перевіряти імена схем у документі OpenRPC. */
class NamesakeDocKernel extends TestKernel
{
    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/namesake_doc';
    }

    protected function configureContainer(ContainerConfigurator $c): void
    {
        parent::configureContainer($c);
        $c->services()->set(NamesakeDocProcedure::class)->autowire()->autoconfigure()->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/group_routes.yaml');
    }
}
