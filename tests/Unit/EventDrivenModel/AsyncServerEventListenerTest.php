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

    public function testChildIntegerResultIsSubstitutedIntoDependentItemAsInteger(): void
    {
        $batch = \Ufo\RpcObject\RpcBatchRequest::fromJson(json_encode([
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'area.count'],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'area.byId', 'params' => ['id' => '@FROM:1()']],
        ]));
        $listener = $this->createListener($this->createMock(RpcServer::class), $this->createMock(RpcCallbackProcessor::class));
        $first = $batch->getCollection()[1];

        // Exactly what a child process prints for an int result.
        $listener->process(new \Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcAsyncOutputEvent(
            $first, $batch, json_encode(['id' => 1, 'result' => 5, 'jsonrpc' => '2.0'], JSON_PRETTY_PRINT)
        ));

        $this->assertSame(5, $batch->getResults()[1]['result']);
        $this->assertSame(5, $batch->getCollection()[2]->getParams()['id']);
    }

    public function testChildFloatResultStaysFloat(): void
    {
        $batch = \Ufo\RpcObject\RpcBatchRequest::fromJson(json_encode([
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'area.price'],
        ]));
        $listener = $this->createListener($this->createMock(RpcServer::class), $this->createMock(RpcCallbackProcessor::class));

        $listener->process(new \Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcAsyncOutputEvent(
            $batch->getCollection()[1], $batch, json_encode(['id' => 1, 'result' => 2.5, 'jsonrpc' => '2.0'])
        ));

        $this->assertSame(2.5, $batch->getResults()[1]['result']);
    }

    public function testChildOutputOfNotificationIsNotAddedToBatch(): void
    {
        $batch = \Ufo\RpcObject\RpcBatchRequest::fromJson(json_encode([
            ['jsonrpc' => '2.0', 'method' => 'area.notify'],
        ]));
        $listener = $this->createListener($this->createMock(RpcServer::class), $this->createMock(RpcCallbackProcessor::class));
        $notification = current($batch->getCollection());

        $listener->process(new \Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcAsyncOutputEvent($notification, $batch, '[]'));

        $this->assertSame([], $batch->getResults());
    }

    /**
     * @return array{0: AsyncServerEventListener, 1: \PHPUnit\Framework\MockObject\MockObject&RpcAsyncProcessor}
     */
    private function detachListener(bool $processMode, ?\Ufo\JsonRpcBundle\Server\RequestPrepare\IRpcRequestCarrier $carrier): array
    {
        $processor = $this->createMock(RpcAsyncProcessor::class);
        $requestCarrier = $this->createMock(\Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier::class);
        $requestCarrier->method('getCarrier')->willReturn($carrier);
        $listener = new AsyncServerEventListener(
            new RpcResponseContextBuilder(),
            Transformer::getDefault(),
            $processor,
            $requestCarrier,
            new \Ufo\JsonRpcBundle\ConfigService\RpcMainConfig(['notification' => ['process' => $processMode]], 'test'),
        );
        return [$listener, $processor];
    }

    private function httpCarrier(bool $batch): \Ufo\JsonRpcBundle\Server\RequestPrepare\Holders\RpcFromHttp
    {
        $carrier = $this->createMock(\Ufo\JsonRpcBundle\Server\RequestPrepare\Holders\RpcFromHttp::class);
        if ($batch) {
            $carrier->method('getBatchRequestObject')->willReturn($this->createMock(\Ufo\RpcObject\RpcBatchRequest::class));
        } else {
            $carrier->method('getBatchRequestObject')->willThrowException(new \Ufo\RpcError\WrongWayException());
        }
        return $carrier;
    }

    private function postResponse(AsyncServerEventListener $listener): void
    {
        $request = new \Ufo\RpcObject\RpcNotificationRequest('area.notify');
        $listener->processAsync(new \Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPostResponseEvent(
            new \Ufo\RpcObject\RpcResponse('n', null),
            $request,
            new \Ufo\JsonRpcBundle\Server\ServiceMap\Service('area.notify', 'App\\Rpc\\Area', new \Ufo\RpcObject\RPC\Info('area')),
        ));
    }

    public function testSingleHttpNotificationIsDetachedInProcessMode(): void
    {
        [$listener, $processor] = $this->detachListener(true, $this->httpCarrier(false));
        $processor->expects($this->once())->method('detach')->willReturn(true);
        $processor->expects($this->never())->method('processAsync');

        $this->postResponse($listener);
    }

    public function testFallsBackToInlineWhenDetachFails(): void
    {
        [$listener, $processor] = $this->detachListener(true, $this->httpCarrier(false));
        $processor->expects($this->once())->method('detach')->willReturn(false);
        $processor->expects($this->once())->method('processAsync');

        $this->postResponse($listener);
    }

    public function testRunsInlineByDefaultInBatchAndInCli(): void
    {
        foreach ([
            'default (process: false)' => [false, $this->httpCarrier(false)],
            'batch item' => [true, $this->httpCarrier(true)],
            'cli (child, detached process, consumer)' => [true, $this->createMock(\Ufo\JsonRpcBundle\Server\RequestPrepare\Holders\RpcFromCli::class)],
        ] as $case => [$processMode, $carrier]) {
            [$listener, $processor] = $this->detachListener($processMode, $carrier);
            $processor->expects($this->never())->method('detach');
            $processor->expects($this->once())->method('processAsync');
            $this->postResponse($listener);
        }
    }

    private function createListener(RpcServer $server, RpcCallbackProcessor $callbackProcessor): AsyncServerEventListener
    {
        $serializer = Transformer::getDefault();
        $processor = new RpcAsyncProcessor(
            $server,
            $serializer,
            $this->createMock(IRpcSecurity::class),
            $callbackProcessor,
            new \Ufo\JsonRpcBundle\Server\ApiGroupContext(),
            new \Ufo\JsonRpcBundle\ConfigService\RpcMainConfig([], 'test'),
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
