<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\Batch;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;
use Ufo\JsonRpcBundle\Server\Batch\InlineBatchStrategy;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Transformer\RpcResponseContextBuilder;

/**
 * Created by hand with the pre-10.4.2 constructor, the strategy still executes an item and fires its POST_RESPONSE.
 */
class InlineBatchStrategyConstructorTest extends TestCase
{
    public function testLegacyConstructorExecutesWithTheGivenHandler(): void
    {
        $eventFactory = new RpcEventFactory(new EventDispatcher());
        $postResponses = 0;
        $eventFactory->addListener(RpcEvent::POST_RESPONSE, function () use (&$postResponses) {
            $postResponses++;
        });

        $handler = $this->createMock(RpcRequestHandler::class);
        $handler->method('provideSingleRequestToResponse')->willReturnCallback(
            function (RpcRequest $request) use ($eventFactory) {
                $response = new RpcResponse($request->getId(), 'ok');
                $eventFactory->fire(RpcEvent::PRE_RESPONSE, $response, $request, $this->createMock(Service::class));
                return $response;
            }
        );

        $strategy = new class(
            $eventFactory,
            $this->createMock(NormalizerInterface::class),
            new RpcResponseContextBuilder(),
            $this->createMock(RpcAsyncProcessor::class),
        ) extends InlineBatchStrategy {
            public function executeItem(RpcRequest $request, RpcRequestHandler $handler): RpcResponse
            {
                return $this->execute($request, $handler);
            }
        };

        $response = $strategy->executeItem(new RpcRequest('1', 'main.ping', []), $handler);

        $this->assertSame('ok', $response->getResult());
        $this->assertSame(1, $postResponses);
    }
}
