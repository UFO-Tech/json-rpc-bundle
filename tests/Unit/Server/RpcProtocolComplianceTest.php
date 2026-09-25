<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\Server;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC\Info;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreResponseEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcRequestEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Listeners\SymfonyErrorListener;
use Ufo\JsonRpcBundle\EventDrivenModel\Listeners\SymfonyFlowListener;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;
use Ufo\JsonRpcBundle\Server\Async\RpcCallbackProcessor;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\JsonRpcBundle\Server\RpcServer;
use Ufo\JsonRpcBundle\Server\RpcCache\RpcCacheService;
use Ufo\JsonRpcBundle\Server\ServiceMap\IServiceHolder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RpcNotificationRequest;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Rules\Validator\RealUrlValidator;
use Ufo\RpcObject\Transformer\RpcResponseContextBuilder;
use Ufo\RpcObject\Transformer\Transformer;

/**
 * TDD expectations from https://www.jsonrpc.org/specification, sections 4, 4.1 and 6.
 */
class RpcProtocolComplianceTest extends TestCase
{
    public function testEmptyBatchReturnsOneInvalidRequestResponse(): void
    {
        $server = $this->createMock(RpcServer::class);
        $server->expects($this->never())->method('handle');

        $response = $this->sendHttpRequest('[]', $server);
        $body = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($body);
        $this->assertArrayHasKey('error', $body, 'An empty batch must return one error object, not an array of responses.');
        $this->assertSame('2.0', $body['jsonrpc']);
        $this->assertSame(-32603, $body['error']['code']);
        $this->assertSame('Can`t process empty batch request', $body['error']['message']);
        $this->assertSame(SymfonyErrorListener::EMPTY_BATCH, $body['id']);
        $this->assertArrayNotHasKey('result', $body);
    }

    public function testServerAcceptsProtocolVersionTwoPointZero(): void
    {
        $server = $this->createMock(RpcServer::class);
        $server->expects($this->once())->method('handle')
            ->willReturnCallback(function (RpcRequest $request): RpcResponse {
                $this->assertFalse($request->hasError());
                $this->assertSame('2.0', $request->getVersion());
                $this->assertSame('testMethod', $request->getMethod());
                $this->assertSame(1, $request->getId());

                return new RpcResponse($request->getId(), ['ok' => true]);
            });

        $response = $this->sendHttpRequest(
            '{"jsonrpc":"2.0","method":"testMethod","id":1}',
            $server
        );
        $body = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('2.0', $body['jsonrpc']);
        $this->assertSame(1, $body['id']);
        $this->assertSame(['ok' => true], $body['result']);
        $this->assertArrayNotHasKey('error', $body);
    }

    public function testServerRejectsUnsupportedProtocolVersion(): void
    {
        $server = $this->createMock(RpcServer::class);
        $server->expects($this->never())->method('handle');

        $response = $this->sendHttpRequest(
            '{"jsonrpc":"3.0","method":"testMethod","id":1}',
            $server
        );
        $body = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($body);
        $this->assertArrayHasKey('error', $body);
        $this->assertSame('2.0', $body['jsonrpc']);
        $this->assertSame(-32603, $body['error']['code']);
        $this->assertArrayNotHasKey('result', $body);
    }

    public function testRequestWithCallbackReturnsAsyncAcknowledgement(): void
    {
        $callbackUrl = 'https://example.com/rpc-callback';
        $service = $this->createMock(Service::class);
        $holder = $this->createMock(IServiceHolder::class);
        $holder->expects($this->once())->method('getService')
            ->with('testMethod')->willReturn($service);


        $eventFactory = $this->createMock(RpcEventFactory::class);
        $eventFactory->expects($this->once())->method('fireRequest')
            ->willReturnCallback(function (RpcRequest $request) use ($callbackUrl): RpcRequestEvent {
                $this->assertFalse($request->hasError());
                $this->assertNotInstanceOf(RpcNotificationRequest::class, $request);
                $this->assertTrue($request->isAsync());
                $this->assertSame(1, $request->getId());
                $this->assertSame('testMethod', $request->getMethod());
                $this->assertSame(['value' => 42], $request->getParams());
                $this->assertSame($callbackUrl, $request->getRpcParams()->getCallbackObject()->getTarget());

                return new RpcRequestEvent($request);
            });
        $eventFactory->expects($this->once())->method('fire')
            ->with(RpcEvent::PRE_RESPONSE, $this->isInstanceOf(RpcResponse::class), $this->isInstanceOf(RpcRequest::class), $service)
            ->willReturnCallback(
                static fn (string $event, RpcResponse $response, RpcRequest $request, Service $service): RpcPreResponseEvent
                    => new RpcPreResponseEvent($response, $request, $service)
            );
        $server = $this->getMockBuilder(RpcServer::class)
            ->setConstructorArgs([$holder, $eventFactory, $this->createMock(RpcCacheService::class)])
            ->onlyMethods(['handle'])
            ->getMock();
        $server->expects($this->never())->method('handle');

        // The dependency probes URLs using its own HTTP client. Cache only this
        // reachability result so parsing stays real without external requests.
        $urlCache = new \ReflectionProperty(RealUrlValidator::class, 'checkedUrls');
        $previousUrls = $urlCache->getValue();
        $urlCache->setValue(null, [$callbackUrl => true] + $previousUrls);
        try {
            $response = $this->sendHttpRequest(json_encode([
                'jsonrpc' => '2.0',
                'method' => 'testMethod',
                'params' => ['value' => 42, '$rpc' => ['callback' => $callbackUrl]],
                'id' => 1,
            ], JSON_THROW_ON_ERROR), $server, $eventFactory);
        } finally {
            $urlCache->setValue(null, $previousUrls);
        }

        $body = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('2.0', $body['jsonrpc']);
        $this->assertSame(1, $body['id']);
        $this->assertTrue($body['result']['async']);
        $this->assertArrayNotHasKey('error', $body);
    }

    public function testRequestWithoutIdIsExecutedWithoutAnHttpResponseBody(): void
    {
        $procedure = $this->createMock(NotificationTestProcedure::class);
        $procedure->expects($this->once())->method('testMethod')
                  ->with(42)
                  ->willReturnCallback(function (int $value): array {
                      // Set a breakpoint here to inspect the actual RPC method invocation.
                      return ['value' => $value];
                  });

        $service = new Service('testMethod', NotificationTestProcedure::class, new Info('test'));
        $holder = $this->createMock(IServiceHolder::class);
        $holder->expects($this->once())->method('getService')
            ->with('testMethod')
            ->willReturnCallback(function (string $method) use ($service): Service {
                return $service;
            });

        $eventFactory = $this->createMock(RpcEventFactory::class);
        $eventFactory->expects($this->once())->method('fireRequest')
            ->willReturnCallback(function (RpcRequest $request): RpcRequestEvent {
                $this->assertInstanceOf(RpcNotificationRequest::class, $request);
                $this->assertFalse($request->hasError());
                $this->assertSame('testMethod', $request->getMethod());
                $this->assertSame(['value' => 42], $request->getParams());

                return new RpcRequestEvent($request);
            });
        $eventFactory->expects($this->once())->method('fire')
            ->with(
                RpcEvent::PRE_RESPONSE,
                $this->isInstanceOf(RpcResponse::class),
                $this->isInstanceOf(RpcNotificationRequest::class),
                $service
            )
            ->willReturnCallback(function (string $event, RpcResponse $response, RpcRequest $request, Service $service) use ($procedure): RpcPreResponseEvent {
                // Run the test procedure inline; this mock does not simulate queue delivery.
                $result = $procedure->testMethod(...$request->getParams());
                $this->assertSame(['value' => 42], $result);

                return new RpcPreResponseEvent($response, $request, $service);
            });

        $server = $this->getMockBuilder(RpcServer::class)
            ->setConstructorArgs([$holder, $eventFactory, $this->createMock(RpcCacheService::class)])
            ->onlyMethods(['handle'])
            ->getMock();
        $server->expects($this->never())->method('handle');

        $response = $this->sendHttpRequest(
            '{"jsonrpc":"2.0","method":"testMethod","params":{"value":42}}',
            $server,
            $eventFactory
        );

        $this->assertSame(204, $response->getStatusCode(), 'Notifications must return 204 status code');
        $this->assertSame('', $response->getContent(), 'Notifications must not produce a JSON-RPC response, including null or [].');
    }

    private function sendHttpRequest(string $content, RpcServer $server, ?RpcEventFactory $eventFactory = null): Response
    {
        $carrier = new RequestCarrier();
        if ($eventFactory === null) {
            $eventFactory = $this->createMock(RpcEventFactory::class);
            $eventFactory->method('fireRequest')->willReturnCallback(
                static fn (RpcRequest $request): RpcRequestEvent => new RpcRequestEvent($request)
            );
        }
        $security = $this->createMock(IRpcSecurity::class);
        $handler = new RpcRequestHandler(
            $carrier,
            $server,
            Transformer::getDefault(),
            $this->createMock(RpcAsyncProcessor::class),
            $this->createMock(RpcCallbackProcessor::class),
            new RpcResponseContextBuilder(),
            $eventFactory,
            $security
        );
        $router = $this->createMock(RouterInterface::class);
        $router->method('match')->willReturn(['_route' => ApiController::API_ROUTE]);
        $flow = new SymfonyFlowListener($carrier, $eventFactory, $handler, $router, $security, new RpcMainConfig([], 'test'));
        $errors = new SymfonyErrorListener($eventFactory, $carrier, $handler, 'test', $router);
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::REQUEST, [$flow, 'initHttpRequest'], 240);
        $dispatcher->addListener(KernelEvents::REQUEST, [$flow, 'magicPostController'], 230);
        $dispatcher->addListener(KernelEvents::EXCEPTION, [$errors, 'requestJsonParseError'], 999);
        $dispatcher->addListener(KernelEvents::EXCEPTION, [$errors, 'otherErrors'], 997);
        $kernel = new HttpKernel($dispatcher, new ControllerResolver());

        return $kernel->handle(Request::create('/rpc', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $content));
    }
}

interface NotificationTestProcedure extends IRpcService
{
    public function testMethod(int $value): array;
}
