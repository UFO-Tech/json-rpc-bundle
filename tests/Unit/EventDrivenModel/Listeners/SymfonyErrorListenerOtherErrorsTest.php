<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\EventDrivenModel\Listeners;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Throwable;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\EventDrivenModel\Listeners\SymfonyErrorListener;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Exceptions\BatchIsDisabledException;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\RpcError\RpcBadRequestException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcNotificationRequest;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

class SymfonyErrorListenerOtherErrorsTest extends TestCase
{
    /**
     * @param RpcRequest[]|null $batch null: not a batch request
     */
    private function handle(?array $batch, Throwable $exception, ?RpcRequest $single = null): Response
    {
        $carrier = $this->createMock(RequestCarrier::class);
        if ($batch === null) {
            $carrier->method('getBatchRequestObject')->willThrowException(new WrongWayException());
            $carrier->method('getRequestObject')->willReturn($single);
        } else {
            $batchRequest = $this->createMock(RpcBatchRequest::class);
            $batchRequest->method('getCollection')->willReturn($batch);
            $carrier->method('getBatchRequestObject')->willReturn($batchRequest);
        }

        $handler = $this->createMock(RpcRequestHandler::class);
        $handler->method('responseToArray')->willReturnCallback(fn(RpcResponse $response) => [
            'id' => $response->getId(),
            'error' => $response->getError()?->getMessage(),
        ]);

        $router = $this->createMock(RouterInterface::class);
        $router->method('match')->willReturn(['_route' => ApiController::API_ROUTE]);

        $listener = new SymfonyErrorListener($this->createMock(RpcEventFactory::class), $carrier, $handler, 'test', $router);
        $event = new ExceptionEvent($this->createMock(HttpKernelInterface::class), Request::create('/rpc', 'POST'), HttpKernelInterface::MAIN_REQUEST, $exception);
        $listener->otherErrors($event);

        return $event->getResponse();
    }

    private function body(Response $response): mixed
    {
        return json_decode($response->getContent(), true);
    }

    public function testBatchErrorAnswersEveryItemExceptNotifications(): void
    {
        $response = $this->handle([new RpcRequest(1, 'a'), new RpcNotificationRequest('n'), new RpcRequest(2, 'b')], new RuntimeException('boom'));

        $this->assertSame([['id' => 1, 'error' => 'boom'], ['id' => 2, 'error' => 'boom']], $this->body($response));
    }

    public function testBatchOfNotificationsOnlyGivesNoContent(): void
    {
        $response = $this->handle([new RpcNotificationRequest('n1'), new RpcNotificationRequest('n2')], new RuntimeException('boom'));

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function testSingleRequestGetsOneErrorObject(): void
    {
        $response = $this->handle(null, new RuntimeException('boom'), new RpcRequest(7, 'a'));

        $this->assertSame(['id' => 7, 'error' => 'boom'], $this->body($response));
    }

    public function testSingleNotificationGivesNoContent(): void
    {
        $response = $this->handle(null, new RuntimeException('boom'), new RpcNotificationRequest('n'));

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function testDisabledBatchGetsOneErrorObject(): void
    {
        $response = $this->handle([new RpcRequest(1, 'a')], new BatchIsDisabledException());

        $this->assertSame(['id' => SymfonyErrorListener::BATCH_DISABLED, 'error' => 'Batch requests are disabled'], $this->body($response));
    }

    public function testEmptyBatchGetsOneErrorObject(): void
    {
        $response = $this->handle([], new RpcBadRequestException('Can`t process empty batch request'));

        $this->assertSame(['id' => SymfonyErrorListener::EMPTY_BATCH, 'error' => 'Can`t process empty batch request'], $this->body($response));
    }
}
