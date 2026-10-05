<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\EventDrivenModel\Listeners;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcErrorEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreExecuteEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Listeners\RpcServerListener;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Locker\LockerService;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Tests\Fixtures\Dto\PersonDto;
use Ufo\JsonRpcBundle\Tests\Fixtures\Procedures\ListenerFixtureProcedure;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\ConstraintsImposedException;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\Transformer\RpcResponseContextBuilder;

class RpcServerListenerTest extends TestCase
{
    /**
     * @var RpcErrorEvent[]
     */
    private array $errorEvents = [];

    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->errorEvents = [];
        $this->dispatcher = new EventDispatcher();
        $this->dispatcher->addListener(RpcEvent::ERROR, function (RpcErrorEvent $event): void {
            $this->errorEvents[] = $event;
        });
    }

    public function testArgumentTypeErrorBecomesConstraintsImposedExceptionKeyedByParamName(): void
    {
        $locker = $this->createMock(LockerService::class);
        $locker->expects($this->once())->method('release');

        $event = $this->createEvent('expectInt', ['abc']);
        $this->createListener($locker)->process($event);

        $exception = $this->singleFiredException();
        $this->assertInstanceOf(ConstraintsImposedException::class, $exception);
        $this->assertSame('Invalid Data for call method: main.expectInt', $exception->getMessage());
        $this->assertSame(
            ['id' => 'Parameter "id" must be of type int, string given'],
            $exception->getConstraintsImposed()
        );
    }

    public function testArgumentTypeErrorMessageIsStrippedOfNamespacesAndCallSite(): void
    {
        $event = $this->createEvent('expectDto', [['name' => 'bob', 'age' => 30]]);
        $this->createListener()->process($event);

        $exception = $this->singleFiredException();
        $this->assertInstanceOf(ConstraintsImposedException::class, $exception);
        $this->assertSame(
            ['person' => 'Parameter "person" must be of type PersonDto, array given'],
            $exception->getConstraintsImposed()
        );
        $this->assertStringNotContainsString(PersonDto::class, $exception->getConstraintsImposed()['person']);
    }

    public function testTypeErrorWithoutArgumentInfoFallsBackToUnknownParam(): void
    {
        $event = $this->createEvent('throwTypeError', []);
        $this->createListener()->process($event);

        $exception = $this->singleFiredException();
        $this->assertInstanceOf(ConstraintsImposedException::class, $exception);
        $this->assertSame(['unknown' => 'custom type failure'], $exception->getConstraintsImposed());
    }

    public function testOtherThrowablesStayRuntimeExceptions(): void
    {
        $event = $this->createEvent('throwRuntime', []);
        $this->createListener()->process($event);

        $exception = $this->singleFiredException();
        $this->assertInstanceOf(AbstractRpcErrorException::class, $exception);
        $this->assertNotInstanceOf(ConstraintsImposedException::class, $exception);
        $this->assertSame('kaboom', $exception->getMessage());
    }

    public function testRequestWithInvalidSpecialParamIsNotExecuted(): void
    {
        $locker = $this->createMock(LockerService::class);
        $locker->expects($this->never())->method('release');

        $request = RpcRequest::fromJson(
            '{"jsonrpc":"2.0","method":"main.expectInt","params":{"id":"abc","$rpc":{"callback":null}},"id":494}'
        );
        $event = new RpcPreExecuteEvent(
            $request,
            new Service('main.expectInt', ListenerFixtureProcedure::class, new Info('main')),
            ['abc']
        );
        $this->createListener($locker)->process($event);

        $exception = $this->singleFiredException();
        $this->assertInstanceOf(RpcBadParamException::class, $exception);
        $this->assertStringContainsString('[params][$rpc][callback]', $exception->getMessage());
        $this->assertTrue($event->isPropagationStopped());
    }

    private function singleFiredException(): \Throwable
    {
        $this->assertCount(1, $this->errorEvents);

        return $this->errorEvents[0]->exception;
    }

    private function createListener(?LockerService $locker = null): RpcServerListener
    {
        return new RpcServerListener(
            $locker ?? $this->createMock(LockerService::class),
            new RpcEventFactory($this->dispatcher),
            new ServiceLocator([
                ListenerFixtureProcedure::class => static fn (): ListenerFixtureProcedure => new ListenerFixtureProcedure(),
            ]),
            new RpcResponseContextBuilder(),
        );
    }

    private function createEvent(string $method, array $params): RpcPreExecuteEvent
    {
        return new RpcPreExecuteEvent(
            new RpcRequest(1, 'main.' . $method),
            new Service('main.' . $method, ListenerFixtureProcedure::class, new Info('main')),
            $params
        );
    }
}
