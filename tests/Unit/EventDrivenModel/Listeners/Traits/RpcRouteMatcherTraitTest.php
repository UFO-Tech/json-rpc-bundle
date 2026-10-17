<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\EventDrivenModel\Listeners\Traits;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Ufo\JsonRpcBundle\EventDrivenModel\Listeners\Traits\RpcRouteMatcherTrait;

class RpcRouteMatcherTraitTest extends TestCase
{
    private function matcher(RouterInterface $router): object
    {
        return new class($router) {
            use RpcRouteMatcherTrait { matchRoute as public; }

            public function __construct(protected RouterInterface $router) {}
        };
    }

    public function testMatchesOncePerRequestAsGetAndRestoresContextMethod(): void
    {
        $context = new RequestContext(method: 'POST');
        $router = $this->createMock(RouterInterface::class);
        $router->method('getContext')->willReturn($context);
        $router->expects($this->once())->method('match')->with('/rpc')
            ->willReturnCallback(function () use ($context): array {
                self::assertSame('GET', $context->getMethod());
                return ['_route' => 'ufo_rpc_api_server'];
            });

        $request = Request::create('/rpc', 'POST');
        $first = $this->matcher($router);
        $second = $this->matcher($router);

        $this->assertSame(['_route' => 'ufo_rpc_api_server'], $first->matchRoute($request));
        $this->assertSame(['_route' => 'ufo_rpc_api_server'], $second->matchRoute($request));
        $this->assertSame('POST', $context->getMethod());
    }

    public function testNoMatchIsAlsoRememberedForTheRequest(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('getContext')->willReturn(new RequestContext());
        $router->expects($this->once())->method('match')->willThrowException(new ResourceNotFoundException());

        $request = Request::create('/app/page');
        $matcher = $this->matcher($router);

        $this->assertSame([], $matcher->matchRoute($request));
        $this->assertSame([], $matcher->matchRoute($request));
    }

    public function testEachRequestIsMatchedSeparately(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('getContext')->willReturn(new RequestContext());
        $router->expects($this->exactly(2))->method('match')->willReturn([]);

        $matcher = $this->matcher($router);
        $matcher->matchRoute(Request::create('/a'));
        $matcher->matchRoute(Request::create('/b'));
    }
}
