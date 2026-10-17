<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\ServiceMap;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;
use Ufo\RpcObject\RPC\Info;

class ServiceMapTest extends TestCase
{
    public function testGetTransportContainsSyncAndAsyncDsnInfo(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('https://api.example.com/rpc'));

        $mainConfig = new RpcMainConfig([
            'docs' => [
                'async_dsn_info' => true,
            ],
            'async' => [
                [
                    'type' => 'amqp',
                    'config' => [
                        'name' => 'rpc_async',
                        'dsn' => 'amqp://guest:guest@127.0.0.1:5672/%2f/messages',
                        'exchange' => 'messages',
                    ],
                ],
            ],
        ], 'test', $stack);

        $map = new ServiceMap([], $mainConfig);
        $transport = $map->getTransport();

        $this->assertArrayHasKey('sync', $transport);
        $this->assertSame('POST', $transport['sync']['method']);
        $this->assertSame('https', $transport['sync']['scheme']);
        $this->assertSame('api.example.com', $transport['sync']['host']);

        $this->assertArrayHasKey('rpc_async', $transport);
        $this->assertSame('amqp', $transport['rpc_async']['scheme']);
        $this->assertSame('127.0.0.1', $transport['rpc_async']['host']);
        $this->assertSame('messages', $transport['rpc_async']['exchange']);
    }

    public function testGetTransportSkipsAsyncWhenDisabledInDocs(): void
    {
        $mainConfig = new RpcMainConfig([
            'docs' => [
                'async_dsn_info' => false,
            ],
            'async' => [
                [
                    'type' => 'amqp',
                    'config' => [
                        'name' => 'rpc_async',
                        'dsn' => 'amqp://guest:guest@127.0.0.1:5672/%2f/messages',
                    ],
                ],
            ],
        ], 'test');

        $map = new ServiceMap([], $mainConfig);
        $transport = $map->getTransport();

        $this->assertArrayHasKey('sync', $transport);
        $this->assertArrayNotHasKey('rpc_async', $transport);
    }

    public function testGetEnvelopeIsStableAcrossCalls(): void
    {
        $map = new ServiceMap([], new RpcMainConfig([], 'test'));

        $first = $map->getEnvelope();
        $second = $map->getEnvelope();

        $this->assertStringStartsWith(ServiceMap::ENV_JSON_RPC_2 . '/UFO-RPC-', $first);
        $this->assertSame($first, $second);
    }

    public function testGetServicesThrowsForUnknownVersion(): void
    {
        $map = new ServiceMap([], new RpcMainConfig([], 'test'));

        $this->expectException(\RuntimeException::class);
        $map->getServices('v999');
    }

    public function testGetVersionsReturnsMapKeys(): void
    {
        $map = new ServiceMap([
            'v1' => [],
            'v2' => [],
        ], new RpcMainConfig([], 'test'));

        $this->assertSame(['v1', 'v2'], $map->getVersions());
    }

    public function testGetServicesReturnsCachedServiceInstances(): void
    {
        $service = new Service('main.ping', 'App\\Rpc\\DummyProcedure', new Info('main'));
        $map = new ServiceMap([
            'v1' => [
                'main.ping' => ['ignored' => true],
            ],
        ], new RpcMainConfig([], 'test'));

        $this->setProtectedProperty($map, 'services', [
            'v1' => [
                'main.ping' => $service,
            ],
        ]);

        $services = $map->getServices('v1');

        $this->assertSame($service, $services['main.ping']);
    }

    public function testGetServiceReturnsCachedInstance(): void
    {
        $service = new Service('main.ping', 'App\\Rpc\\DummyProcedure', new Info('main'));
        $map = new ServiceMap([
            'v1' => [
                'main.ping' => ['ignored' => true],
            ],
        ], new RpcMainConfig([], 'test'));

        $this->setProtectedProperty($map, 'services', [
            'v1' => [
                'main.ping' => $service,
            ],
        ]);

        $this->assertSame($service, $map->getService('main.ping', 'v1'));
    }

    private function setProtectedProperty(object $object, string $name, mixed $value): void
    {
        $ref = new \ReflectionObject($object);
        $property = $ref->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /** @var array<class-string<\Ufo\DTO\DTOTransformer>> */
    private array $bootedTransformers = [];

    protected function tearDown(): void
    {
        foreach ($this->bootedTransformers as $transformer) {
            $transformer::reset();
        }
        $this->bootedTransformers = [];
    }

    private function serviceData(string $fqcn): array
    {
        foreach ([\Ufo\DTO\DTOTransformer::class, \Ufo\DTO\ServiceTransformer::class] as $transformer) {
            if (!$transformer::isInitialized()) {
                $transformer::boot(\Ufo\DTO\Factory\DefaultDTOTransformerFactory::default()->create($transformer));
                $this->bootedTransformers[] = $transformer;
            }
        }
        return (new Service('main.ping', $fqcn, new Info('main')))->toArray();
    }

    public function testHydratedServiceIsReusedFromMetadataCacheByNextRequest(): void
    {
        $pool = new \Symfony\Component\Cache\Adapter\ArrayAdapter();
        $config = new RpcMainConfig([], 'test');
        $data = $this->serviceData('App\\Rpc\\Ping');

        $first = new ServiceMap([Info::DEFAULT_VERSION => ['main.ping' => $data]], $config, metadataCache: new \Ufo\JsonRpcBundle\Server\RpcCache\RpcMetadataCache($pool, 'b1'));
        $first->getService('main.ping');

        // Next request: same container build, map data deliberately different - the cached object must be used.
        $changed = $this->serviceData('App\\Rpc\\Changed');
        $second = new ServiceMap([Info::DEFAULT_VERSION => ['main.ping' => $changed]], $config, metadataCache: new \Ufo\JsonRpcBundle\Server\RpcCache\RpcMetadataCache($pool, 'b1'));

        $this->assertSame('App\\Rpc\\Ping', $second->getService('main.ping')->procedureFQCN);
    }

    public function testApiGroupsAreCachedSeparately(): void
    {
        $pool = new \Symfony\Component\Cache\Adapter\ArrayAdapter();
        $config = new RpcMainConfig([], 'test');
        $groupMaps = [
            'bfa' => [Info::DEFAULT_VERSION => ['main.ping' => $this->serviceData('App\\Rpc\\BfaPing')]],
            'bff' => [Info::DEFAULT_VERSION => ['main.ping' => $this->serviceData('App\\Rpc\\BffPing')]],
        ];
        $context = new \Ufo\JsonRpcBundle\Server\ApiGroupContext();
        $cache = new \Ufo\JsonRpcBundle\Server\RpcCache\RpcMetadataCache($pool, 'b1');

        $context->setGroup('bfa');
        $bfa = (new ServiceMap([], $config, $context, $groupMaps, metadataCache: $cache))->getService('main.ping');
        $context->setGroup('bff');
        $bff = (new ServiceMap([], $config, $context, $groupMaps, metadataCache: $cache))->getService('main.ping');

        $this->assertSame('App\\Rpc\\BfaPing', $bfa->procedureFQCN);
        $this->assertSame('App\\Rpc\\BffPing', $bff->procedureFQCN);
    }
}
