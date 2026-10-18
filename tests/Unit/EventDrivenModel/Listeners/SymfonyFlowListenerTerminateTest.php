<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\EventDrivenModel\Listeners;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Listeners\SymfonyFlowListener;
use Ufo\JsonRpcBundle\EventDrivenModel\PostResponseQueue;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Server\RequestPrepare\Holders\RpcFromHttp;
use Ufo\JsonRpcBundle\Server\RequestPrepare\IRpcHttpCarrier;
use Ufo\JsonRpcBundle\Server\RequestPrepare\IRpcRequestCarrier;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcError;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

class SymfonyFlowListenerTerminateTest extends TestCase
{
    private EventDispatcher $dispatcher;
    private RpcEventFactory $eventFactory;
    private PostResponseQueue $queue;
    private int $postResponses = 0;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->eventFactory = new RpcEventFactory($this->dispatcher);
        $this->queue = new PostResponseQueue($this->eventFactory);
        $this->postResponses = 0;
        $this->dispatcher->addListener(RpcEvent::POST_RESPONSE, function () {
            $this->postResponses++;
        });
    }

    private function listener(?IRpcRequestCarrier $carrier): SymfonyFlowListener
    {
        $requestCarrier = $this->createMock(RequestCarrier::class);
        $requestCarrier->method('getCarrier')->willReturn($carrier);

        return new SymfonyFlowListener(
            $requestCarrier,
            $this->eventFactory,
            $this->createMock(RpcRequestHandler::class),
            $this->createMock(RouterInterface::class),
            $this->createMock(IRpcSecurity::class),
            new RpcMainConfig([], 'test'),
            postResponseQueue: $this->queue,
        );
    }

    private function preResponse(?Service $service = null, ?RpcError $error = null): RpcRequest
    {
        $rpcRequest = new RpcRequest('1', 'main.ping', []);
        $this->eventFactory->fire(
            RpcEvent::PRE_RESPONSE,
            new RpcResponse('1', $error ? null : 'ok', $error),
            $rpcRequest,
            $service ?? $this->createMock(Service::class),
        );
        return $rpcRequest;
    }

    private function terminate(SymfonyFlowListener $listener, Request $request): void
    {
        $listener->firePostResponseEvent(
            new TerminateEvent($this->createMock(HttpKernelInterface::class), $request, new Response())
        );
    }

    private function httpCarrier(Request $request): IRpcHttpCarrier
    {
        return new class($request) implements IRpcHttpCarrier {
            public function __construct(private Request $request) {}
            public function getRequestObject(): RpcRequest { throw new WrongWayException(); }
            public function getBatchRequestObject(): RpcBatchRequest { throw new WrongWayException(); }
            public function getHttpRequest(): Request { return $this->request; }
        };
    }

    public function testKernelTerminateKeepsOtherListenersRunning(): void
    {
        $request = new Request();
        $carrier = $this->createMock(RpcFromHttp::class);
        $carrier->method('getHttpRequest')->willReturn($request);
        $this->preResponse();

        $appListenerCalled = false;
        $this->dispatcher->addListener(KernelEvents::TERMINATE, [$this->listener($carrier), 'firePostResponseEvent'], 1000000);
        $this->dispatcher->addListener(KernelEvents::TERMINATE, function () use (&$appListenerCalled) {
            $appListenerCalled = true;
        });

        $event = new TerminateEvent($this->createMock(HttpKernelInterface::class), $request, new Response());
        $this->dispatcher->dispatch($event, KernelEvents::TERMINATE);

        $this->assertSame(1, $this->postResponses);
        $this->assertFalse($event->isPropagationStopped());
        $this->assertTrue($appListenerCalled);
    }

    public function testConsoleTerminateKeepsOtherListenersRunning(): void
    {
        $this->preResponse();

        $appListenerCalled = false;
        $this->dispatcher->addListener(ConsoleEvents::TERMINATE, [$this->listener(null), 'firePostResponseEvent'], 1000000);
        $this->dispatcher->addListener(ConsoleEvents::TERMINATE, function () use (&$appListenerCalled) {
            $appListenerCalled = true;
        });

        $event = new ConsoleTerminateEvent(new Command('test'), new ArrayInput([]), new NullOutput(), Command::SUCCESS);
        $this->dispatcher->dispatch($event, ConsoleEvents::TERMINATE);

        $this->assertSame(1, $this->postResponses);
        $this->assertFalse($event->isPropagationStopped());
        $this->assertTrue($appListenerCalled);
    }

    public function testAnyHttpCarrierOfThisRequestGetsPostResponse(): void
    {
        $request = new Request();
        $this->preResponse();

        $this->terminate($this->listener($this->httpCarrier($request)), $request);

        $this->assertSame(1, $this->postResponses);
    }

    public function testCarrierOfAnotherRequestGetsNone(): void
    {
        $this->preResponse();

        $this->terminate($this->listener($this->httpCarrier(new Request())), new Request());

        $this->assertSame(0, $this->postResponses);
    }

    public function testErrorResponseGetsNone(): void
    {
        $request = new Request();
        $rpcRequest = $this->preResponse();
        // The error response of the same request comes last and has no service.
        $this->eventFactory->fire(RpcEvent::PRE_RESPONSE, new RpcResponse('1', error: new RpcError(-32000, 'boom', [])), $rpcRequest, null);

        $this->terminate($this->listener($this->httpCarrier($request)), $request);

        $this->assertSame(0, $this->postResponses);
    }

    public function testEveryExecutedRequestGetsOne(): void
    {
        $request = new Request();
        $this->preResponse();
        $this->preResponse();

        $this->terminate($this->listener($this->httpCarrier($request)), $request);

        $this->assertSame(2, $this->postResponses);
    }

    public function testDeferredRequestsFireWithoutOwnCarrierAndOnlyOnce(): void
    {
        $request = new Request();
        $this->preResponse();
        // As RpcExecutor does for an adapter whose carrier is not an HTTP one.
        $this->queue->defer($this->queue->takeFromPool(), $request);
        $listener = $this->listener(null);

        $this->terminate($listener, $request);
        $this->terminate($listener, $request);

        $this->assertSame(1, $this->postResponses);
    }

    public function testDeferredAndOwnPoolDoNotDuplicate(): void
    {
        $request = new Request();
        $this->preResponse();
        $this->queue->defer($this->queue->takeFromPool(), $request);

        $this->terminate($this->listener($this->httpCarrier($request)), $request);

        $this->assertSame(1, $this->postResponses);
    }
}
