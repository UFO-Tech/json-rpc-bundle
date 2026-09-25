<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\EventDrivenModel;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPostResponseEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Listeners\AsyncServerEventListener;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;
use Ufo\JsonRpcBundle\Server\Async\RpcCallbackProcessor;
use Ufo\JsonRpcBundle\Server\RpcServer;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Rules\Validator\RealUrlValidator;
use Ufo\RpcObject\Transformer\RpcResponseContextBuilder;
use Ufo\RpcObject\Transformer\Transformer;

class AsyncServerEventListenerTest extends TestCase
{
    public function testProcessAsyncExecutesRequestBeforeSendingCallback(): void
    {
        $request = $this->createAsyncRequest();
        $response = new RpcResponse($request->getId(), ['value' => 42]);
        $server = $this->createMock(RpcServer::class);
        $server->expects($this->once())->method('handle')
            ->with($this->identicalTo($request))
            ->willReturnCallback(function (RpcRequest $request) use ($response): RpcResponse {
                $request->setResponse($response);

                return $response;
            });
        $callbackProcessor = $this->createMock(RpcCallbackProcessor::class);
        $callbackProcessor->expects($this->once())->method('process')
            ->with($this->identicalTo($request))
            ->willReturnCallback(function (RpcRequest $request) use ($response): void {
                $this->assertSame($response, $request->getResponseObject());
            });

        $listener = $this->createListener($server, $callbackProcessor);
        $this->processEvent($listener, $request);
    }

    public function testProcessAsyncSkipsSynchronousRequest(): void
    {
        $request = new RpcRequest(1, 'testMethod', ['value' => 42]);
        $server = $this->createMock(RpcServer::class);
        $server->expects($this->never())->method('handle');
        $callbackProcessor = $this->createMock(RpcCallbackProcessor::class);
        $callbackProcessor->expects($this->never())->method('process');

        $listener = $this->createListener($server, $callbackProcessor);
        $this->processEvent($listener, $request);
    }

    public function testProcessAsyncPropagatesServerExceptionWithoutSendingCallback(): void
    {
        $request = $this->createAsyncRequest();
        $exception = new RuntimeException('Processing failed');
        $server = $this->createMock(RpcServer::class);
        $server->expects($this->once())->method('handle')
            ->with($this->identicalTo($request))
            ->willThrowException($exception);
        $callbackProcessor = $this->createMock(RpcCallbackProcessor::class);
        $callbackProcessor->expects($this->never())->method('process');

        $listener = $this->createListener($server, $callbackProcessor);
        $this->expectExceptionObject($exception);

        $this->processEvent($listener, $request);
    }

    private function createAsyncRequest(): RpcRequest
    {
        $callbackUrl = 'https://example.com/rpc-callback';
        // Avoid the dependency's network probe; construct and validate the request normally.
        $urlCache = new \ReflectionProperty(RealUrlValidator::class, 'checkedUrls');
        $previousUrls = $urlCache->getValue();
        $urlCache->setValue(null, [$callbackUrl => true] + $previousUrls);
        try {
            $request = RpcRequest::fromArray([
                'jsonrpc' => '2.0',
                'method' => 'testMethod',
                'params' => ['value' => 42, '$rpc' => ['callback' => $callbackUrl]],
                'id' => 1,
            ]);
        } finally {
            $urlCache->setValue(null, $previousUrls);
        }

        $this->assertFalse($request->hasError());
        $this->assertTrue($request->isAsync());

        return $request;
    }

    private function createListener(RpcServer $server, RpcCallbackProcessor $callbackProcessor): AsyncServerEventListener
    {
        $serializer = Transformer::getDefault();
        $processor = new RpcAsyncProcessor(
            $server,
            $serializer,
            $this->createMock(IRpcSecurity::class),
            $callbackProcessor
        );

        return new AsyncServerEventListener(
            new RpcResponseContextBuilder(),
            $serializer,
            $processor
        );
    }

    private function processEvent(AsyncServerEventListener $listener, RpcRequest $request): void
    {
        // The real processor writes diagnostic output to stdout.
        ob_start();
        try {
            $listener->processAsync($this->createEvent($request));
        } finally {
            ob_end_clean();
        }
    }

    private function createEvent(RpcRequest $request): RpcPostResponseEvent
    {
        return new RpcPostResponseEvent(
            new RpcResponse(1, ['async' => true]),
            $request,
            $this->createMock(Service::class)
        );
    }
}
