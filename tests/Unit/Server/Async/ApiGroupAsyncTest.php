<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\Async;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;
use Ufo\JsonRpcBundle\Server\Async\RpcCallbackProcessor;
use Ufo\JsonRpcBundle\Server\RpcServer;
use Ufo\RpcObject\RpcAsyncRequest;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Transformer\Transformer;

class ApiGroupAsyncTest extends TestCase
{
    public function testMessageUsesItsGroupForSecurityAndExecutionAndResetsAfterwards(): void
    {
        $context = new ApiGroupContext();
        $request = new RpcRequest(1, 'area.shared');
        $security = $this->createMock(IRpcSecurity::class);
        $security->expects($this->once())->method('isValidApiRequest')->willReturnCallback(function () use ($context): true {
            self::assertSame('bfa', $context->getGroup());
            return true;
        });
        $server = $this->createMock(RpcServer::class);
        $server->expects($this->once())->method('handle')->willReturnCallback(function (RpcRequest $request) use ($context): RpcResponse {
            self::assertSame('bfa', $context->getGroup());
            return new RpcResponse($request->getId(), 'ok');
        });
        $processor = new RpcAsyncProcessor($server, Transformer::getDefault(), $security, $this->createMock(RpcCallbackProcessor::class), $context);
        ob_start();
        try {
            $processor(new RpcAsyncRequest($request, 'token', apiGroup: 'bfa'));
        } finally {
            ob_end_clean();
        }
        self::assertSame('general', $context->getGroup());
    }

    public function testMessageResetsGroupWhenSecurityRejectsToken(): void
    {
        $context = new ApiGroupContext();
        $security = $this->createMock(IRpcSecurity::class);
        $security->method('isValidApiRequest')->willThrowException(new \RuntimeException('rejected'));
        $server = $this->createMock(RpcServer::class);
        $server->expects($this->never())->method('handle');
        $processor = new RpcAsyncProcessor($server, Transformer::getDefault(), $security, $this->createMock(RpcCallbackProcessor::class), $context);
        try {
            $processor(new RpcAsyncRequest(new RpcRequest(1, 'area.shared'), apiGroup: 'bfa'));
            self::fail('Expected token rejection');
        } catch (\RuntimeException $e) {
            self::assertSame('rejected', $e->getMessage());
        }
        self::assertSame('general', $context->getGroup());
    }
}
