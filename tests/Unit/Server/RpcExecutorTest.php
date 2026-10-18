<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\Server;

use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcErrorEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\PostResponseQueue;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Server\RequestPrepare\IRpcRequestCarrier;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\JsonRpcBundle\Server\RpcExecutor;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcError;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

class RpcExecutorTest extends TestCase
{
    private RpcEventFactory $eventFactory;
    private PostResponseQueue $queue;
    private RequestCarrier $requestCarrier;
    private RequestStack $requestStack;
    /** @var \Throwable[] */
    private array $errors = [];

    protected function setUp(): void
    {
        $dispatcher = new EventDispatcher();
        $this->eventFactory = new RpcEventFactory($dispatcher);
        $this->queue = new PostResponseQueue($this->eventFactory);
        $this->requestCarrier = new RequestCarrier();
        $this->requestStack = new RequestStack();
        $this->errors = [];
        // What RpcServerListener::onRpcError does.
        $dispatcher->addListener(RpcEvent::ERROR, function (RpcErrorEvent $event) {
            $this->errors[] = $event->exception;
            $event->rpcRequest->setResponse(new RpcResponse($event->rpcRequest->getId(), error: $event->rpcError));
        });
    }

    private function executor(callable $handle): RpcExecutor
    {
        $handler = $this->createMock(RpcRequestHandler::class);
        $handler->method('provideSingleRequestToResponse')->willReturnCallback($handle);

        return new RpcExecutor($handler, $this->requestCarrier, $this->eventFactory, $this->queue, $this->requestStack);
    }

    private function carrier(RpcRequest $request): IRpcRequestCarrier
    {
        return new class($request) implements IRpcRequestCarrier {
            public function __construct(private RpcRequest $request) {}
            public function getRequestObject(): RpcRequest { return $this->request; }
            public function getBatchRequestObject(): RpcBatchRequest { throw new WrongWayException(); }
        };
    }

    private function succeed(RpcRequest $request): RpcResponse
    {
        $response = new RpcResponse($request->getId(), 'ok');
        $request->setResponse($response);
        $this->eventFactory->fire(RpcEvent::PRE_RESPONSE, $response, $request, $this->createMock(Service::class));
        return $response;
    }

    public function testSuccessSetsCarrierAndDefersPostResponseToTheMainRequest(): void
    {
        $http = new Request();
        $this->requestStack->push($http);
        $request = new RpcRequest('1', 'main.ping', []);
        $carrier = $this->carrier($request);

        $response = $this->executor(fn(RpcRequest $r) => $this->succeed($r))->execute($carrier);

        $this->assertSame('ok', $response->getResult());
        $this->assertSame($carrier, $this->requestCarrier->getCarrier());
        $this->assertTrue($this->queue->hasPending($http));
        $this->assertFalse($this->queue->hasPending());
    }

    public function testEscapedRpcExceptionBecomesErrorResponse(): void
    {
        $request = new RpcRequest('1', 'main.missing', []);
        $exception = new RpcMethodNotFoundExceptionRpc('not found');

        $response = $this->executor(fn() => throw $exception)->execute($this->carrier($request));

        $this->assertInstanceOf(RpcError::class, $response->getError());
        $this->assertSame([$exception], $this->errors);
        $this->assertFalse($this->queue->hasPending());
    }

    public function testEscapedNonRpcExceptionIsWrapped(): void
    {
        $request = new RpcRequest('1', 'main.ping', []);

        $response = $this->executor(fn() => throw new LogicException('boom'))->execute($this->carrier($request));

        $this->assertNotNull($response->getError());
        $this->assertInstanceOf(AbstractRpcErrorException::class, $this->errors[0]);
        $this->assertSame('boom', $this->errors[0]->getMessage());
    }

    public function testEveryCallIsDeferredSeparately(): void
    {
        $executor = $this->executor(fn(RpcRequest $r) => $this->succeed($r));
        $fired = 0;
        $this->eventFactory->addListener(RpcEvent::POST_RESPONSE, function () use (&$fired) {
            $fired++;
        });

        $executor->executeRequest(new RpcRequest('1', 'main.ping', []));
        $executor->executeRequest(new RpcRequest('2', 'main.ping', []));
        $this->queue->flush();

        $this->assertSame(2, $fired);
    }
}
