<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\Server;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcRequestEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcTokenHolder;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;
use Ufo\JsonRpcBundle\Server\Async\RpcCallbackProcessor;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\JsonRpcBundle\Server\RpcCache\RpcCacheService;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\JsonRpcBundle\Server\RpcServer;
use Ufo\JsonRpcBundle\Server\ServiceMap\IServiceHolder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\SpecialRpcParams;
use Ufo\RpcObject\Transformer\RpcResponseContextBuilder;

class RpcRequestHandlerTest extends TestCase
{
    /**
     * @return SerializerInterface&NormalizerInterface&MockObject
     */
    protected function getSerializerNormalizer(): SerializerInterface&NormalizerInterface&MockObject
    {
        /**
         * @var SerializerInterface&NormalizerInterface&MockObject
         */
        return $this->createMockForIntersectionOfInterfaces([
            SerializerInterface::class,
            NormalizerInterface::class,
        ]);
    }
    public function testHandleFallsBackToSingleRequestWhenNotBatch(): void
    {
        $request = $this->createMock(RpcRequest::class);
        $carrier = $this->createMock(RequestCarrier::class);
        $carrier->method('getBatchRequestObject')->willThrowException(new WrongWayException());
        $carrier->expects($this->once())->method('getRequestObject')->willReturn($request);

        $handler = $this->getMockBuilder(RpcRequestHandler::class)
            ->setConstructorArgs($this->createBaseConstructorArgs($carrier))
            ->onlyMethods(['provideSingleRequest'])
            ->getMock()
        ;
        $handler->expects($this->once())->method('provideSingleRequest')->with($request)->willReturn(['ok' => true]);

        $this->assertSame(['ok' => true], $handler->handle());
    }

    public function testHandleBatchProcessesQueueAndReturnsBatchResults(): void
    {
        $queueRequest = $this->createMock(RpcRequest::class);
        $queueRequest->expects($this->once())->method('refreshRawJson');
        $queueRequest->method('getRpcParams')->willReturn(new SpecialRpcParams(null, 5));

        $unprocessedRequest = $this->createMock(RpcRequest::class);
        $response = new RpcResponse('9', ['ok' => true]);

        $batch = new class($queueRequest, $unprocessedRequest) extends RpcBatchRequest {
            private array $ready;
            private array $unprocessed;
            private array $results = [];

            public function __construct(RpcRequest $queueRequest, RpcRequest $unprocessedRequest)
            {
                $this->ready = ['q1' => $queueRequest];
                $this->unprocessed = ['u1' => $unprocessedRequest];
            }

            public function &getReadyToHandle(): array
            {
                return $this->ready;
            }

            public function provideUnprocessedRequests(): array
            {
                return $this->unprocessed;
            }

            public function addResponse(RpcResponse $response, array $result): static
            {
                $this->results[$response->getId()] = $result;
                return $this;
            }

            public function getResults(bool $withKeys = true): array
            {
                return $withKeys ? $this->results : array_values($this->results);
            }
        };

        $carrier = $this->createMock(RequestCarrier::class);
        $carrier->expects($this->once())->method('getBatchRequestObject')->willReturn($batch);

        $asyncProcessor = $this->createMock(RpcAsyncProcessor::class);
        $asyncProcessor->expects($this->once())
            ->method('createProcesses')
            ->with(
                $queueRequest,
                'ufo-rpc-token',
                'token',
                [],
                null,
                null,
                null,
                5.0
            )
        ;
        $asyncProcessor->expects($this->once())->method('process');

        $handler = $this->getMockBuilder(RpcRequestHandler::class)
            ->setConstructorArgs([
                $carrier,
                $this->createMock(RpcServer::class),
                $this->getSerializerNormalizer(),
                $asyncProcessor,
                $this->createMock(RpcCallbackProcessor::class),
                new RpcResponseContextBuilder(),
                $this->createMock(RpcEventFactory::class),
                $this->createBaseConstructorArgs()[7],
            ])
            ->onlyMethods(['provideSingleRequestToResponse', 'responseToArray'])
            ->getMock()
        ;
        $handler->expects($this->once())->method('provideSingleRequestToResponse')->with($unprocessedRequest)->willReturn($response);
        $handler->expects($this->once())->method('responseToArray')->with($response)->willReturn(['id' => '9']);

        $this->assertSame([['id' => '9']], $handler->handle());
        $this->addToAssertionCount(1);
    }

    public function testProvideSingleRequestConvertsResponseToArray(): void
    {
        $request = $this->createMock(RpcRequest::class);
        $response = new RpcResponse('1', ['ok' => true]);

        $handler = $this->getMockBuilder(RpcRequestHandler::class)
            ->setConstructorArgs($this->createBaseConstructorArgs())
            ->onlyMethods(['provideSingleRequestToResponse', 'responseToArray'])
            ->getMock()
        ;
        $handler->expects($this->once())->method('provideSingleRequestToResponse')->with($request)->willReturn($response);
        $handler->expects($this->once())->method('responseToArray')->with($response)->willReturn(['id' => '1']);

        $this->assertSame(['id' => '1'], $handler->provideSingleRequest($request));
    }

    public function testResponseToArrayUsesSerializerWithContextBuilder(): void
    {
        $serializer = $this->getSerializerNormalizer();
        $response = new RpcResponse('1', ['ok' => true]);

        $serializer->expects($this->once())
            ->method('normalize')
            ->with($response, null, $this->isType('array'))
            ->willReturn(['id' => '1', 'result' => ['ok' => true]])
        ;

        $handler = new RpcRequestHandler(
            $this->createMock(RequestCarrier::class),
            $this->createMock(RpcServer::class),
            $serializer,
            $this->createMock(RpcAsyncProcessor::class),
            $this->createMock(RpcCallbackProcessor::class),
            new RpcResponseContextBuilder(),
            $this->createMock(RpcEventFactory::class),
            $this->createMock(IRpcSecurity::class)
        );

        $this->assertSame(['id' => '1', 'result' => ['ok' => true]], $handler->responseToArray($response));
    }

    public function testProvideSingleRequestToResponseSyncUsesRpcServerHandle(): void
    {
        $request = $this->createMock(RpcRequest::class);
        $request->method('isAsync')->willReturn(false);
        $response = new RpcResponse('1', ['ok' => true]);

        $eventFactory = $this->createMock(RpcEventFactory::class);
        $eventFactory->expects($this->once())->method('fireRequest')->with($request)->willReturn(new RpcRequestEvent($request));

        $rpcServer = $this->createMock(RpcServer::class);
        $rpcServer->expects($this->once())->method('handle')->with($request)->willReturn($response);

        $handler = new RpcRequestHandler(
            $this->createMock(RequestCarrier::class),
            $rpcServer,
            $this->getSerializerNormalizer(),
            $this->createMock(RpcAsyncProcessor::class),
            $this->createMock(RpcCallbackProcessor::class),
            new RpcResponseContextBuilder(),
            $eventFactory,
            $this->createMock(IRpcSecurity::class)
        );

        $this->assertSame($response, $handler->provideSingleRequestToResponse($request));
    }

    public function testProvideSingleRequestToResponseAsyncBuildsAsyncResponseAndFiresPreResponse(): void
    {
        $request = $this->createMock(RpcRequest::class);
        $request->method('isAsync')->willReturn(true);
        $request->method('getId')->willReturn('42');
        $request->method('getMethod')->willReturn('main.ping');
        $request->method('getVersion')->willReturn('2.0');
        $request->expects($this->once())->method('setResponse');

        $request->method('getRpcParams')->willReturn(new SpecialRpcParams('https://example.com/callback'));

        $service = new Service('main.ping', 'App\\Rpc\\DummyProcedure', new Info('main'));

        $holder = $this->createMock(IServiceHolder::class);
        $holder->expects($this->once())->method('getService')->with('main.ping')->willReturn($service);

        $rpcServer = new RpcServer(
            $holder,
            $this->createMock(RpcEventFactory::class),
            $this->createMock(RpcCacheService::class)
        );

        $firedPreResponse = false;
        $eventFactory = $this->createMock(RpcEventFactory::class);
        $eventFactory->expects($this->once())->method('fireRequest')->with($request)->willReturn(new RpcRequestEvent($request));
        $eventFactory->expects($this->once())
            ->method('fire')
            ->willReturnCallback(function (string $eventName) use (&$firedPreResponse): RpcEvent {
                $firedPreResponse = ($eventName === RpcEvent::PRE_RESPONSE);
                return new RpcEvent([]);
            })
        ;

        $handler = new RpcRequestHandler(
            $this->createMock(RequestCarrier::class),
            $rpcServer,
            $this->getSerializerNormalizer(),
            $this->createMock(RpcAsyncProcessor::class),
            $this->createMock(RpcCallbackProcessor::class),
            new RpcResponseContextBuilder(),
            $eventFactory,
            $this->createMock(IRpcSecurity::class)
        );

        $response = $handler->provideSingleRequestToResponse($request);

        $this->assertInstanceOf(RpcResponse::class, $response);
        $this->assertSame('42', (string)$response->getId());
        $this->assertTrue($firedPreResponse);
    }

    /**
     * @return array<int,mixed>
     */
    private function createBaseConstructorArgs(?RequestCarrier $carrier = null): array
    {
        $rpcSecurity = $this->createMock(IRpcSecurity::class);
        $tokenHolder = $this->createMock(IRpcTokenHolder::class);
        $tokenHolder->method('getTokenKey')->willReturn('Ufo-RPC-Token');
        $tokenHolder->method('getToken')->willReturn('token');
        $rpcSecurity->method('getTokenHolder')->willReturn($tokenHolder);

        return [
            $carrier ?? $this->createMock(RequestCarrier::class),
            $this->createMock(RpcServer::class),
            $this->getSerializerNormalizer(),
            $this->createMock(RpcAsyncProcessor::class),
            $this->createMock(RpcCallbackProcessor::class),
            new RpcResponseContextBuilder(),
            $this->createMock(RpcEventFactory::class),
            $rpcSecurity,
        ];
    }
}
