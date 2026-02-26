<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\ServiceMap;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\JsonRpcBundle\Server\ServiceMap\IServiceHolder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceHolder;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\Info;

class ServiceHolderTest extends TestCase
{
    public function testGenerateServiceCacheName(): void
    {
        $this->assertSame(
            'service_ping_v1',
            ServiceHolder::generateServiceCacheName('ping', 'v1')
        );
    }

    public function testGetServiceReturnsCachedService(): void
    {
        $service = $this->createService('main.ping');
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())->method('get')->willReturn($service);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())->method('getItem')->willReturn($cacheItem);

        $holder = new ServiceHolder([], $cache);

        $this->assertSame($service, $holder->getService('main.ping', 'v1'));
    }

    public function testGetServiceFallsBackToTaggedHoldersWhenCacheFails(): void
    {
        $service = $this->createService('main.ping');

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())->method('getItem')->willThrowException(new \RuntimeException('cache down'));

        $fallbackHolder = $this->createMock(IServiceHolder::class);
        $fallbackHolder->expects($this->once())
            ->method('getService')
            ->with('main.ping', 'v1')
            ->willReturn($service);

        $holder = new ServiceHolder([$fallbackHolder], $cache);

        $this->assertSame($service, $holder->getService('main.ping', 'v1'));
    }

    public function testGetServiceThrowsWhenNotFoundAnywhere(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willThrowException(new \RuntimeException('cache down'));

        $fallbackHolder = $this->createMock(IServiceHolder::class);
        $fallbackHolder->method('getService')->willThrowException(new ServiceNotFoundException('nope'));

        $holder = new ServiceHolder([$fallbackHolder], $cache);

        $this->expectException(ServiceNotFoundException::class);
        $holder->getService('unknown', 'v1');
    }

    public function testGetServicesThrowsWrongWayException(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $holder = new ServiceHolder([], $cache);

        $this->expectException(WrongWayException::class);
        $holder->getServices();
    }

    public function testGetVersionsReturnsUniqueVersionsFromHolders(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);

        $holderA = $this->createMock(IServiceHolder::class);
        $holderA->method('getVersions')->willReturn(['v1', 'v2']);

        $holderB = $this->createMock(IServiceHolder::class);
        $holderB->method('getVersions')->willReturn(['v2', 'v3']);

        $holder = new ServiceHolder([$holderA, $holderB], $cache);

        $this->assertSame(['v1', 'v2', 'v3'], array_values($holder->getVersions()));
    }

    private function createService(string $name): Service
    {
        return new Service($name, 'App\\Rpc\\DummyProcedure', new Info('main'));
    }
}

