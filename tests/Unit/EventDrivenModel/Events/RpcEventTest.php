<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\EventDrivenModel\Events;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcAsyncOutputEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcRequestEvent;
use Ufo\RpcObject\RpcRequest;

class RpcEventTest extends TestCase
{
    public function testCreateReturnsTypedEventForKnownName(): void
    {
        $request = RpcRequest::fromArray([
            'id' => 1,
            'jsonrpc' => '2.0',
            'method' => 'main.ping',
        ]);

        $event = RpcEvent::create(RpcEvent::REQUEST, [$request]);

        $this->assertInstanceOf(RpcRequestEvent::class, $event);
        $this->assertSame(RpcEvent::REQUEST, $event->getEventName());
    }

    public function testCreateFallsBackToGenericRpcEventForUnknownName(): void
    {
        $event = RpcEvent::create('rpc.unknown', ['a' => 1]);

        $this->assertInstanceOf(RpcEvent::class, $event);
        $this->assertSame(RpcEvent::NAME, $event->getEventName());
    }

    public function testGetEventFqsnForKnownAndUnknownNames(): void
    {
        $this->assertSame(
            RpcAsyncOutputEvent::class,
            RpcEvent::getEventFQSN(RpcEvent::OUTPUT_ASYNC)
        );

        $this->expectException(\InvalidArgumentException::class);
        RpcEvent::getEventFQSN('rpc.missing');
    }
}
