<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\Async;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;
use Ufo\JsonRpcBundle\Server\Async\RpcCallbackProcessor;
use Ufo\JsonRpcBundle\Server\RpcServer;
use Ufo\RpcObject\RpcNotificationRequest;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Transformer\Transformer;

class RpcAsyncProcessorProcessAsyncTest extends TestCase
{
    public function testExecutesWithoutPrintingAndLogs(): void
    {
        $request = new RpcNotificationRequest('area.notify', ['id' => 1]);
        $server = $this->createMock(RpcServer::class);
        $server->expects($this->once())->method('handle')->with($request)->willReturn(new RpcResponse('n', 'done'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with(
            $this->anything(),
            $this->callback(fn(array $context) => $context['method'] === 'area.notify'
                && $context['params'] === ['id' => 1]
                && $context['result'] === 'done')
        );

        $processor = new RpcAsyncProcessor(
            $server,
            Transformer::getDefault(),
            $this->createMock(IRpcSecurity::class),
            $this->createMock(RpcCallbackProcessor::class),
            new ApiGroupContext(),
            new RpcMainConfig([], 'test'),
            logger: $logger,
        );

        // Nothing may be printed: output to a closed HTTP connection aborts the script before the method runs.
        $this->expectOutputString('');
        $processor->processAsync($request);
    }
}
