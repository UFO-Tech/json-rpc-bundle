<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Ufo\JsonRpcBundle\Routing\RpcYamlFileLoader;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;

class ApiYamlFileLoaderTest extends TestCase
{
    private string $directory;
    private ApiGroupContext $context;
    private RpcYamlFileLoader $loader;
    private Loader $registration;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/ufo-api-routing-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
        $this->context = new ApiGroupContext();
        $this->loader = new class(new FileLocator($this->directory), 'test', $this->context) extends RpcYamlFileLoader {
            protected function getRouteNamePrefixAttribute(array $config, string $path): ?string
            {
                if ($this->isResourceImport($config['resource'], $path, dirname($path).'/adapter.yaml')) {
                    return '_adapter_name_prefix';
                }
                return parent::getRouteNamePrefixAttribute($config, $path);
            }
        };
        $this->registration = new class($this->context) extends Loader {
            public array $groups = [];
            public bool $fail = false;

            public function __construct(private ApiGroupContext $context)
            {
                parent::__construct();
            }

            public function load(mixed $resource, ?string $type = null): RouteCollection
            {
                $this->groups[] = $this->context->getGroup();
                if ($this->fail) {
                    throw new \RuntimeException('Registration failed');
                }
                $routes = new RouteCollection();
                $routes->add('generated', new Route('/'.$this->context->getGroup()), 25);
                return $routes;
            }

            public function supports(mixed $resource, ?string $type = null): bool
            {
                return $type === 'test_api';
            }
        };
        $resolver = new LoaderResolver([$this->loader, $this->registration]);
        $this->loader->setResolver($resolver);
        $this->registration->setResolver($resolver);
        file_put_contents($this->directory.'/adapter.yaml', "api:\n    resource: generated\n    type: test_api\n");
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    private function load(string $yaml): RouteCollection
    {
        file_put_contents($this->directory.'/imports.yaml', $yaml);
        return $this->loader->load('imports.yaml');
    }

    public function testAdapterRoutesAreRegisteredInTheirGroupAndKeepPriorities(): void
    {
        $this->context->setGroup('previous', 'custom_');
        $routes = $this->load("api:\n    resource: adapter.yaml\n    prefix: /api\n    name_prefix: second_\n    defaults: { api_group: internal }\n");

        self::assertSame(['internal'], $this->registration->groups);
        $route = $routes->get('internal_second_generated');
        self::assertNotNull($route);
        self::assertSame('/api/internal', $route->getPath());
        self::assertSame('internal', $route->getDefault(ApiGroupContext::ROUTE_GROUP));
        self::assertSame('internal_second_', $route->getDefault('_adapter_name_prefix'));
        self::assertSame(25, $routes->getPriority('internal_second_generated'));
        self::assertSame('previous', $this->context->getGroup());
        self::assertSame('custom_route', $this->context->getRouteName('route'));
    }

    public function testGeneralRoutesRetainLegacyAliases(): void
    {
        $routes = $this->load("api:\n    resource: adapter.yaml\n");
        self::assertSame('general_generated', $routes->getAlias('generated')->getId());
        self::assertSame('general_', $routes->get('general_generated')->getDefault('_adapter_name_prefix'));
    }

    public function testRepeatedImportsDoNotLeakThePreviousGroup(): void
    {
        $routes = $this->load("internal:\n    resource: adapter.yaml\n    defaults: { api_group: internal }\ngeneral:\n    resource: adapter.yaml\npublic:\n    resource: adapter.yaml\n    defaults: { api_group: public }\n");
        self::assertSame(['internal', 'general', 'public'], $this->registration->groups);
        self::assertSame(['internal_generated', 'general_generated', 'public_generated'], array_keys($routes->all()));
        self::assertSame('general', $this->context->getGroup());
    }

    public function testContextIsRestoredWhenRegistrationThrows(): void
    {
        $this->context->setGroup('previous', 'custom_');
        $this->registration->fail = true;
        try {
            $this->load("api:\n    resource: adapter.yaml\n    defaults: { api_group: internal }\n");
            self::fail('Expected import failure.');
        } catch (\Exception $error) {
            self::assertStringContainsString('Registration failed', $error->getMessage());
        }
        self::assertSame(['internal'], $this->registration->groups);
        self::assertSame('previous', $this->context->getGroup());
        self::assertSame('custom_route', $this->context->getRouteName('route'));
    }

    public function testSubclassStillHandlesRpcImportsAndLeavesOtherRoutesUntouched(): void
    {
        file_put_contents($this->directory.'/rpc.yaml', "rpc:\n    path: /rpc\n    defaults: { _rpc_route: rpc }\nother:\n    path: /other\n");
        $routes = $this->load("api:\n    resource: rpc.yaml\n    defaults: { api_group: internal }\n");
        self::assertNotNull($routes->get('internal_rpc'));
        self::assertSame('internal_', $routes->get('internal_rpc')->getDefault(ApiGroupContext::ROUTE_NAME_PREFIX));
        self::assertNotNull($routes->get('other'));
        self::assertNull($routes->get('other')->getDefault(ApiGroupContext::ROUTE_NAME_PREFIX));
        self::assertNull($routes->get('internal_other'));
    }

    public function testPlainYamlImportsAreNotGrouped(): void
    {
        file_put_contents($this->directory.'/plain.yaml', "plain:\n    path: /plain\n");
        $routes = $this->load("other:\n    resource: plain.yaml\n");
        self::assertSame(['plain'], array_keys($routes->all()));
        self::assertSame([], $routes->get('plain')->getDefaults());
    }

    public function testInvalidGroupIsRejectedBeforeRegistration(): void
    {
        try {
            $this->load("api:\n    resource: adapter.yaml\n    defaults: { api_group: '' }\n");
            self::fail('Expected invalid group.');
        } catch (\InvalidArgumentException $error) {
            self::assertStringContainsString('non-empty string', $error->getMessage());
        }
        self::assertSame([], $this->registration->groups);
    }

    public function testOriginalRpcConstructorRemainsSupportedWithoutContext(): void
    {
        $loader = new RpcYamlFileLoader(new FileLocator($this->directory), 'test');
        $loader->setResolver(new LoaderResolver([$loader]));
        file_put_contents($this->directory.'/rpc.yaml', "rpc:\n    path: /rpc\n    defaults: { _rpc_route: rpc }\n");
        file_put_contents($this->directory.'/imports.yaml', "api:\n    resource: rpc.yaml\n    defaults: { api_group: internal }\n");
        self::assertNotNull($loader->load('imports.yaml')->get('internal_rpc'));
    }
}
