<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\Security\TokenHolders;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\Security\TokenHolders\RpcAsyncTokenHolder;
use Ufo\RpcObject\RpcAsyncRequest;
use Ufo\RpcObject\RpcRequest;

class RpcAsyncTokenHolderTest extends TestCase
{
    public function testReturnsDefaultTokenKey(): void
    {
        $request = new RpcAsyncRequest(RpcRequest::fromArray([
            'id' => 1,
            'jsonrpc' => '2.0',
            'method' => 'ping',
        ]));
        $holder = new RpcAsyncTokenHolder($request);

        $this->assertSame('token', $holder->getTokenKey());
    }

    public function testReturnsTokenFromAsyncRequest(): void
    {
        $request = new RpcAsyncRequest(
            RpcRequest::fromArray([
                'id' => 1,
                'jsonrpc' => '2.0',
                'method' => 'ping',
            ]),
            'secret-token'
        );
        $holder = new RpcAsyncTokenHolder($request);

        $this->assertSame('secret-token', $holder->getToken());
    }
}

