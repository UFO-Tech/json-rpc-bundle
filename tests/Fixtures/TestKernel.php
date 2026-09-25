<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Ufo\JsonRpcBundle\UfoJsonRpcBundle;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** Конфіг ufo_json_rpc для конкретного тесту (KernelTestCase не вміє передавати аргументи). */
    public static array $rpcConfig = [];

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new SecurityBundle(), new UfoJsonRpcBundle()];
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/ufo-json-rpc-bundle-test/cache/' . md5(serialize(self::$rpcConfig));
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/ufo-json-rpc-bundle-test/log';
    }

    protected function configureContainer(ContainerConfigurator $c): void
    {
        $c->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
        ]);

        $c->extension('security', [
            'providers' => ['in_memory' => ['memory' => null]],
            'firewalls' => ['main' => ['lazy' => true]],
        ]);

        $c->extension('ufo_json_rpc', self::$rpcConfig ?: [
            'cache' => [],
            'security' => ['protected_api' => false, 'protected_doc' => false],
            'docs' => ['project_name' => 'test-rpc', 'validations' => []],
        ]);

        $c->services()->defaults()->public()->autowire()->autoconfigure();
    }
}
