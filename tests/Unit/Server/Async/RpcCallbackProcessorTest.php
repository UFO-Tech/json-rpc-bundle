<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\Async;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Ufo\JsonRpcBundle\Server\Async\RpcCallbackProcessor;
use Ufo\JsonRpcBundle\Server\RequestPrepare\IRpcRequestCarrier;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\SpecialRpcParams;

class RpcCallbackProcessorTest extends TestCase
{
    public function testProcessForwardsOnlyWhitelistedHeadersAndPostsToCallback(): void
    {
        $request = Request::create('/rpc', 'POST');
        $request->headers->set('Authorization', 'Bearer secret');
        $request->headers->set('X-Request-Id', 'req-1');
        $request->headers->set('Traceparent', '00-test');

        $carrier = new class($request) implements IRpcRequestCarrier {
            public function __construct(private Request $request) {}

            public function getRequestObject(): RpcRequest
            {
                throw new WrongWayException();
            }

            public function getBatchRequestObject(): \Ufo\RpcObject\RpcBatchRequest
            {
                throw new WrongWayException();
            }

            public function getHttpRequest(): Request
            {
                return $this->request;
            }
        };

        $requestCarrier = $this->createMock(RequestCarrier::class);
        $requestCarrier->method('getCarrier')->willReturn($carrier);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('serialize')
            ->willReturn('{"jsonrpc":"2.0","id":"1","result":{"ok":true}}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://example.com/callback',
                $this->callback(function (array $options): bool {
                    $headers = $options['headers'] ?? [];

                    return isset($headers['x-request-id'], $headers['traceparent'], $headers['Content-Type'], $headers['Accept'])
                        && !isset($headers['authorization'])
                        && $options['timeout'] === 10.0
                        && $options['max_duration'] === 15.0;
                })
            )
            ->willReturn($response);

        $processor = new RpcCallbackProcessor($client, $serializer, $requestCarrier);

        $rpcRequest = $this->createMock(RpcRequest::class);
        $rpcRequest->method('getRpcParams')->willReturn(new SpecialRpcParams('https://example.com/callback'));
        $rpcRequest->method('getResponseObject')->willReturn(new RpcResponse('1', ['ok' => true]));

        $processor->process($rpcRequest);
    }

    public function testProcessThrowsOnNonSuccessfulCallbackStatus(): void
    {
        $requestCarrier = $this->createMock(RequestCarrier::class);
        $requestCarrier->method('getCarrier')->willReturn($this->createMock(IRpcRequestCarrier::class));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('serialize')->willReturn('{"jsonrpc":"2.0","id":"1","result":{"ok":true}}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);

        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('request')
            ->with('POST', 'https://example.com/callback', $this->isType('array'))
            ->willReturn($response);

        $processor = new RpcCallbackProcessor($client, $serializer, $requestCarrier);

        $rpcRequest = $this->createMock(RpcRequest::class);
        $rpcRequest->method('getRpcParams')->willReturn(new SpecialRpcParams('https://example.com/callback'));
        $rpcRequest->method('getResponseObject')->willReturn(new RpcResponse('1', ['ok' => true]));

        $this->expectException(RpcAsyncRequestException::class);
        $this->expectExceptionMessage('Callback endpoint returned HTTP status code: 500');

        $processor->process($rpcRequest);
    }

    public function testPrepareHeadersKeepsOnlyWhitelistedHeaders(): void
    {
        $processor = new RpcCallbackProcessor(
            $this->createMock(HttpClientInterface::class),
            $this->createMock(SerializerInterface::class),
            $this->createMock(RequestCarrier::class)
        );

        $headers = new HeaderBag([
            'x-request-id' => ['req-1'],
            'traceparent' => ['00-abcd'],
            'authorization' => ['Bearer token'],
        ]);

        $method = new \ReflectionMethod($processor, 'prepareHeaders');
        $method->setAccessible(true);
        $result = $method->invoke($processor, $headers);

        $this->assertSame([
            'x-request-id' => 'req-1',
            'traceparent' => '00-abcd',
        ], $result);
    }
}
