<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\EventDrivenModel;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\Exception\EventSourceException;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcErrorEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcRequestEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\RpcObject\RpcRequest;

class RpcEventFactoryTest extends TestCase
{
    public function testFireWithStringCreatesAndDispatchesTypedEvent(): void
    {
        $dispatcher = new EventDispatcher();
        $factory = new RpcEventFactory($dispatcher);
        $request = RpcRequest::fromArray([
            'id' => 1,
            'jsonrpc' => '2.0',
            'method' => 'main.ping',
        ]);

        $received = null;
        $factory->addListener(RpcEvent::REQUEST, function (RpcRequestEvent $event) use (&$received): void {
            $received = $event;
        });

        $event = $factory->fire(RpcEvent::REQUEST, $request);

        $this->assertInstanceOf(RpcRequestEvent::class, $event);
        $this->assertSame($event, $received);
        $this->assertSame($event, $factory->getEvent(RpcRequestEvent::class));
    }

    public function testFireRequestAndFireErrorReturnTypedEvents(): void
    {
        $factory = new RpcEventFactory(new EventDispatcher());
        $request = RpcRequest::fromArray([
            'id' => 2,
            'jsonrpc' => '2.0',
            'method' => 'main.ping',
        ]);
        $throwable = new \RuntimeException('boom');

        $requestEvent = $factory->fireRequest($request);
        $errorEvent = $factory->fireError($request, $throwable);

        $this->assertSame($request, $requestEvent->rpcRequest);
        $this->assertInstanceOf(RpcErrorEvent::class, $errorEvent);
        $this->assertSame($throwable, $errorEvent->exception);
    }

    public function testFireAsyncRequestAndGetEventDispatcher(): void
    {
        $dispatcher = new EventDispatcher();
        $factory = new RpcEventFactory($dispatcher);
        $request = RpcRequest::fromArray([
            'id' => 3,
            'jsonrpc' => '2.0',
            'method' => 'main.ping',
        ]);

        $event = $factory->fireAsyncRequest($request);

        $this->assertSame($dispatcher, $factory->getEventDispatcher());
        $this->assertSame(RpcEvent::REQUEST_ASYNC, $event->getEventName());
    }

    public function testGetEventThrowsWhenEventNotFound(): void
    {
        $factory = new RpcEventFactory(new EventDispatcher());

        $this->expectException(EventSourceException::class);
        $factory->getEvent(RpcRequestEvent::class);
    }
}
