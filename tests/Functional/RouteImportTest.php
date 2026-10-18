<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\JsonRpcBundle\Tests\Fixtures\RouteImportKernel;
use Ufo\JsonRpcBundle\Tests\Fixtures\TestKernel;

class RouteImportTest extends KernelTestCase
{
    protected static function getKernelClass(): string { return RouteImportKernel::class; }

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        TestKernel::$rpcConfig = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testRegisteredAdapterRouterCanBeImportedOncePerGroup(): void
    {
        self::bootKernel();
        $routes = self::getContainer()->get('router')->getRouteCollection();

        foreach (['adapter' => '/adapter', 'internal' => '/adapter/internal'] as $group => $prefix) {
            $route = $routes->get($group.'_adapter_endpoint');
            self::assertNotNull($route, $group);
            self::assertSame($prefix.'/', $route->getPath());
            self::assertSame($group, $route->getDefault(ApiGroupContext::ROUTE_GROUP));
            self::assertSame($group.'_', $route->getDefault('_adapter_name_prefix'));
            self::assertNotNull($routes->get($group.'_adapter_manifest'));
        }
    }
}
