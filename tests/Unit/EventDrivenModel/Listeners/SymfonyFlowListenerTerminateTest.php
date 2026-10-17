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
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreResponseEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Listeners\SymfonyFlowListener;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Server\RequestPrepare\Holders\RpcFromHttp;
use Ufo\JsonRpcBundle\Server\RequestPrepare\IRpcRequestCarrier;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

class SymfonyFlowListenerTerminateTest extends TestCase
{
    private function listener(?IRpcRequestCarrier $carrier): SymfonyFlowListener
    {
        $requestCarrier = $this->createMock(RequestCarrier::class);
        $requestCarrier->method('getCarrier')->willReturn($carrier);

        $eventFactory = $this->createMock(RpcEventFactory::class);
        $eventFactory->method('getEvent')->willReturn(new RpcPreResponseEvent(
            $this->createMock(RpcResponse::class),
            $this->createMock(RpcRequest::class),
            null,
        ));
        $eventFactory->expects($this->once())->method('fire')->with(RpcEvent::POST_RESPONSE);

        return new SymfonyFlowListener(
            $requestCarrier,
            $eventFactory,
            $this->createMock(RpcRequestHandler::class),
            $this->createMock(RouterInterface::class),
            $this->createMock(IRpcSecurity::class),
            new RpcMainConfig([], 'test'),
        );
    }

    public function testKernelTerminateKeepsOtherListenersRunning(): void
    {
        $request = new Request();
        $carrier = $this->createMock(RpcFromHttp::class);
        $carrier->method('getHttpRequest')->willReturn($request);

        $appListenerCalled = false;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::TERMINATE, [$this->listener($carrier), 'firePostResponseEvent'], 1000000);
        $dispatcher->addListener(KernelEvents::TERMINATE, function () use (&$appListenerCalled) {
            $appListenerCalled = true;
        });

        $event = new TerminateEvent($this->createMock(HttpKernelInterface::class), $request, new Response());
        $dispatcher->dispatch($event, KernelEvents::TERMINATE);

        $this->assertFalse($event->isPropagationStopped());
        $this->assertTrue($appListenerCalled);
    }

    public function testConsoleTerminateKeepsOtherListenersRunning(): void
    {
        $appListenerCalled = false;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ConsoleEvents::TERMINATE, [$this->listener(null), 'firePostResponseEvent'], 1000000);
        $dispatcher->addListener(ConsoleEvents::TERMINATE, function () use (&$appListenerCalled) {
            $appListenerCalled = true;
        });

        $event = new ConsoleTerminateEvent(new Command('test'), new ArrayInput([]), new NullOutput(), Command::SUCCESS);
        $dispatcher->dispatch($event, ConsoleEvents::TERMINATE);

        $this->assertFalse($event->isPropagationStopped());
        $this->assertTrue($appListenerCalled);
    }
}
