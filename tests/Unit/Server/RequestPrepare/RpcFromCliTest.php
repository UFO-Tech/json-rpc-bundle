<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\RequestPrepare;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RpcFromCli;
use Ufo\RpcError\RpcJsonParseException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RpcRequest;

class RpcFromCliTest extends TestCase
{
    /**
     * Test to verify that getRequestObject retrieves a valid RpcRequest when inputJson is properly formatted for a single request.
     *
     * @throws RpcJsonParseException
     */
    public function testGetRequestObjectValidRpcRequest(): void
    {
        $inputJson = json_encode(['jsonrpc' => '2.0', 'method' => 'testMethod', 'id' => 1]);
        $rpcFromCli = new RpcFromCli($inputJson);
        $requestObject = $rpcFromCli->getRequestObject();
        $this->assertInstanceOf(RpcRequest::class, $requestObject);
        $this->assertSame('testMethod', $requestObject->getMethod());
        $this->assertSame(1, $requestObject->getId());
    }

    /**
     * Test to verify that getRequestObject throws WrongWayException when batch input is provided.
     *
     * @throws RpcJsonParseException
     */
    public function testGetRequestObjectThrowsExceptionOnBatchRequest(): void
    {
        $this->expectException(WrongWayException::class);
        $inputJson = json_encode([
            ['jsonrpc' => '2.0', 'method' => 'testMethod1', 'id' => 1],
            ['jsonrpc' => '2.0', 'method' => 'testMethod2', 'id' => 2],
        ]);
        $rpcFromCli = new RpcFromCli($inputJson);
        $rpcFromCli->getRequestObject(); // Should throw WrongWayException
    }

    /**
     * Test to verify that getRequestObject throws WrongWayException when no requestObject is set.
     */
    public function testGetRequestObjectThrowsExceptionWhenNotSet(): void
    {
        $this->expectException(RpcJsonParseException::class);
        $inputJson = '{'; // Invalid request JSON
        $rpcFromCli = new RpcFromCli($inputJson);
        $rpcFromCli->getRequestObject();
    }

}