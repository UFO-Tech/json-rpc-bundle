<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\RequestPrepare;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\Server\RequestPrepare\Holders\RpcFromHttp;
use Ufo\RpcError\RpcJsonParseException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcRequest;

class RpcFromHttpTest extends TestCase
{
    public function testGetRequestObjectReturnsRpcRequest(): void
    {
        $content = json_encode(['jsonrpc' => '2.0', 'method' => 'testMethod']);
        $request = new Request([], [], [], [], [], [], $content);
        $rpcFromHttp = new RpcFromHttp($request);
        $rpcRequest = $rpcFromHttp->getRequestObject();
        $this->assertInstanceOf(RpcRequest::class, $rpcRequest);
        $this->assertEquals('testMethod', $rpcRequest->getMethod());
    }

    public function testGetRequestObjectThrowsWrongWayExceptionWhenBatchRequestExists(): void
    {
        $content = json_encode([
            ['jsonrpc' => '2.0', 'method' => 'testMethod1'],
            ['jsonrpc' => '2.0', 'method' => 'testMethod2'],
        ]);
        $request = new Request([], [], [], [], [], [], $content);
        $rpcFromHttp = new RpcFromHttp($request);
        $this->expectException(WrongWayException::class);
        $rpcFromHttp->getRequestObject();
    }

    /**
     * @throws RpcJsonParseException
     */
    public function testGetRequestObjectThrowsWrongWayExceptionWhenNoRequestObjectExists(): void
    {
        $this->expectException(RpcJsonParseException::class);
        $request = new Request([], [], [], [], [], [], '');
        $rpcFromHttp = new RpcFromHttp($request);
        $rpcFromHttp->getRequestObject();
    }

    public function testInvalidJsonThrowsRpcJsonParseException(): void
    {
        $this->expectException(RpcJsonParseException::class);
        $request = new Request([], [], [], [], [], [], 'invalid-json');
        new RpcFromHttp($request);
    }

    public function testGetBatchRequestObjectReturnsBatchForBatchInput(): void
    {
        $content = json_encode([
            ['jsonrpc' => '2.0', 'method' => 'testMethod1', 'id' => 1],
            ['jsonrpc' => '2.0', 'method' => 'testMethod2', 'id' => 2],
        ]);
        $request = new Request([], [], [], [], [], [], $content);
        $rpcFromHttp = new RpcFromHttp($request);

        $batch = $rpcFromHttp->getBatchRequestObject();

        $this->assertInstanceOf(RpcBatchRequest::class, $batch);
        $this->assertCount(2, $batch->getCollection());
    }

    public function testGetHttpRequestReturnsOriginalRequest(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'testMethod',
            'id' => 1,
        ]));
        $rpcFromHttp = new RpcFromHttp($request);

        $this->assertSame($request, $rpcFromHttp->getHttpRequest());
    }

}
