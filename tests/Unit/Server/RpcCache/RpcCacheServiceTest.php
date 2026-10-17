<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\RpcCache;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Server\RpcCache\RpcCacheService;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers\ChainServiceFiller;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\Cache;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

class RpcCacheServiceTest extends TestCase
{
    private function cacheService(CacheItemPoolInterface $pool): RpcCacheService
    {
        $config = new RpcMainConfig([], 'prod');
        return new RpcCacheService(
            $pool,
            $config,
            new ServiceMap([], $config),
            $this->createMock(ContainerInterface::class),
            new ChainServiceFiller([]),
            $this->createMock(RpcEventFactory::class),
        );
    }

    private function service(string $fqcn = 'App\\Rpc\\Area', int $version = 1, bool $cached = true): Service
    {
        $service = new Service('area.who', $fqcn, new Info('area', version: $version));
        if ($cached) {
            $service->setAttribute(new Cache(Cache::T_MINUTE, [Cache::ENV_PROD]));
        }
        return $service;
    }

    private function request(array $params = ['id' => 7]): RpcRequest
    {
        return new RpcRequest(1, 'area.who', $params);
    }

    private function cachedResponse(RpcRequest $request, mixed $result): RpcResponse
    {
        return new RpcResponse($request->getId(), $result, cache: new Cache(Cache::T_MINUTE, [Cache::ENV_PROD]));
    }

    public function testSameImplementationHasTheSameKey(): void
    {
        $cache = $this->cacheService(new ArrayAdapter());

        $this->assertSame(
            $cache->getCacheKey($this->request(), $this->service()),
            $cache->getCacheKey($this->request(), $this->service())
        );
    }

    public function testDifferentImplementationsUnderOneNameDoNotCollide(): void
    {
        $cache = $this->cacheService(new ArrayAdapter());

        $this->assertNotSame(
            $cache->getCacheKey($this->request(), $this->service('App\\Rpc\\Area')),
            $cache->getCacheKey($this->request(), $this->service('App\\Rpc\\BffArea'))
        );
    }

    public function testDifferentVersionsDoNotCollide(): void
    {
        $cache = $this->cacheService(new ArrayAdapter());

        $this->assertNotSame(
            $cache->getCacheKey($this->request(), $this->service(version: 1)),
            $cache->getCacheKey($this->request(), $this->service(version: 2))
        );
    }

    public function testDifferentParamsDoNotCollide(): void
    {
        $cache = $this->cacheService(new ArrayAdapter());

        $this->assertNotSame(
            $cache->getCacheKey($this->request(['id' => 1]), $this->service()),
            $cache->getCacheKey($this->request(['id' => 2]), $this->service())
        );
    }

    public function testRoundTripPerImplementation(): void
    {
        $cache = $this->cacheService(new ArrayAdapter());
        $request = $this->request();
        $cache->saveCacheResponse($request, $this->cachedResponse($request, 'bfa'), $this->service('App\\Rpc\\Area'));

        $this->assertSame('bfa', $cache->getCacheResponse($request, $this->service('App\\Rpc\\Area'))->getResult());

        $this->expectException(WrongWayException::class);
        $cache->getCacheResponse($request, $this->service('App\\Rpc\\BffArea'));
    }

    public function testNonCacheableMethodDoesNoCacheIo(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->never())->method('getItem');
        $pool->expects($this->never())->method('save');

        $cache = $this->cacheService($pool);
        $request = $this->request();

        try {
            $cache->getCacheResponse($request, $this->service(cached: false));
            $this->fail('Expected WrongWayException');
        } catch (WrongWayException) {}
        $cache->saveCacheResponse($request, new RpcResponse($request->getId(), 'x'), $this->service(cached: false));
    }

    public function testMethodCachedForOtherEnvironmentDoesNoCacheIo(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->never())->method('getItem');

        $service = new Service('area.who', 'App\\Rpc\\Area', new Info('area'));
        $service->setAttribute(new Cache(Cache::T_MINUTE, [Cache::ENV_DEV]));

        $this->expectException(WrongWayException::class);
        $this->cacheService($pool)->getCacheResponse($this->request(), $service);
    }

    public function testMissDoesNotWrite(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $item = (new ArrayAdapter())->getItem('x');
        $pool->expects($this->once())->method('getItem')->willReturn($item);
        $pool->expects($this->never())->method('save');
        $pool->expects($this->never())->method('deleteItem');

        $this->expectException(WrongWayException::class);
        $this->cacheService($pool)->getCacheResponse($this->request(), $this->service());
    }
}
