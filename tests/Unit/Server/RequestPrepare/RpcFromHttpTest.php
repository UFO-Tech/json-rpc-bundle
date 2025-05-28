<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\RequestPrepare;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RpcFromHttp;
use Ufo\RpcError\RpcJsonParseException;
use Ufo\RpcError\WrongWayException;
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

}