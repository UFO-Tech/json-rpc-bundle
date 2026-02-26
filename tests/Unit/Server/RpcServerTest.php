<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\Server;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreResponseEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\JsonRpcBundle\Server\RpcCache\RpcCacheService;
use Ufo\JsonRpcBundle\Server\RpcServer;
use Ufo\JsonRpcBundle\Server\ServiceMap\IServiceHolder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

class RpcServerTest extends TestCase
{
    public function testHandleReturnsCachedResponseWhenAvailable(): void
    {
        $request = $this->createMock(RpcRequest::class);
        $request->method('getRpcParams')->willReturn(null);
        $request->method('hasError')->willReturn(false);
        $request->expects($this->once())->method('setResponse');

        $response = new RpcResponse('1', ['ok' => true]);

        $cache = $this->createMock(RpcCacheService::class);
        $cache->expects($this->once())->method('getCacheResponse')->with($request)->willReturn($response);
        $cache->expects($this->once())->method('saveCacheResponse')->with($request, $response);

        $server = new RpcServer(
            $this->createMock(IServiceHolder::class),
            $this->createMock(RpcEventFactory::class),
            $cache
        );

        $this->assertSame($response, $server->handle($request));
    }

    public function testHandleFallsBackToRpcExecutionWhenCacheMissed(): void
    {
        $request = $this->createMock(RpcRequest::class);
        $request->method('getRpcParams')->willReturn(null);
        $request->method('hasError')->willReturn(false);
        $request->expects($this->once())->method('setResponse');

        $response = new RpcResponse('2', ['ok' => true]);

        $cache = $this->createMock(RpcCacheService::class);
        $cache->method('getCacheResponse')->willThrowException(new WrongWayException());
        $cache->expects($this->once())->method('saveCacheResponse')->with($request, $response);

        $server = $this->getMockBuilder(RpcServer::class)
            ->setConstructorArgs([
                $this->createMock(IServiceHolder::class),
                $this->createMock(RpcEventFactory::class),
                $cache,
            ])
            ->onlyMethods(['handleRpcRequest'])
            ->getMock()
        ;
        $server->expects($this->once())->method('handleRpcRequest')->with($request)->willReturn($response);

        $this->assertSame($response, $server->handle($request));
    }

    public function testHandleDoesNotSaveCacheForErrorRequest(): void
    {
        $request = $this->createMock(RpcRequest::class);
        $request->method('getRpcParams')->willReturn(null);
        $request->method('hasError')->willReturn(true);
        $request->expects($this->once())->method('setResponse');

        $response = new RpcResponse('3', ['ok' => false]);

        $cache = $this->createMock(RpcCacheService::class);
        $cache->method('getCacheResponse')->willThrowException(new WrongWayException());
        $cache->expects($this->never())->method('saveCacheResponse');

        $server = $this->getMockBuilder(RpcServer::class)
            ->setConstructorArgs([
                $this->createMock(IServiceHolder::class),
                $this->createMock(RpcEventFactory::class),
                $cache,
            ])
            ->onlyMethods(['handleRpcRequest'])
            ->getMock()
        ;
        $server->method('handleRpcRequest')->willReturn($response);

        $this->assertSame($response, $server->handle($request));
    }

    public function testHandleRpcRequestThrowsRpcMethodNotFoundWhenServiceMissing(): void
    {
        $request = RpcRequest::fromArray([
            'id' => 1,
            'jsonrpc' => '2.0',
            'method' => 'main.missing',
            'params' => [],
        ]);

        $holder = $this->createMock(IServiceHolder::class);
        $holder->expects($this->once())
            ->method('getService')
            ->with('main.missing', 'v1')
            ->willThrowException(new ServiceNotFoundException('not found'))
        ;

        $server = new RpcServer(
            $holder,
            $this->createMock(RpcEventFactory::class),
            $this->createMock(RpcCacheService::class)
        );

        $this->expectException(RpcMethodNotFoundExceptionRpc::class);
        $server->handleRpcRequest($request);
    }

    public function testHandleRpcRequestReturnsResponseFromPreResponseListener(): void
    {
        $request = RpcRequest::fromArray([
            'id' => 11,
            'jsonrpc' => '2.0',
            'method' => 'main.ping',
        ]);
        $service = new Service('main.ping', 'App\\Rpc\\DummyProcedure', new Info('main'));
        $response = new RpcResponse($request->getId(), ['pong' => true], requestObject: $request);

        $holder = $this->createMock(IServiceHolder::class);
        $holder->method('getService')->willReturn($service);

        $eventFactory = $this->createMock(RpcEventFactory::class);
        $listener = null;

        $eventFactory->expects($this->once())
            ->method('addListener')
            ->with(RpcEvent::PRE_RESPONSE, $this->isType('callable'), -100000)
            ->willReturnCallback(function (string $eventName, callable $callback) use (&$listener): void {
                $listener = $callback;
            })
        ;

        $eventFactory->expects($this->once())
            ->method('fire')
            ->with(RpcEvent::PRE_EXECUTE, $request, $service, [])
            ->willReturnCallback(function () use (&$listener, $response, $request, $service): RpcEvent {
                $request->setResponse($response);
                $listener(new RpcPreResponseEvent($response, $request, $service));
                return new RpcEvent([]);
            })
        ;

        $server = new RpcServer(
            $holder,
            $eventFactory,
            $this->createMock(RpcCacheService::class)
        );
        $server->newRequest($request);

        $this->assertSame($response, $server->handleRpcRequest($request));
    }
}

