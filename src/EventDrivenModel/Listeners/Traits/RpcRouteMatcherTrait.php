<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners\Traits;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\RouterInterface;

/**
 * Matches the request path against the RPC routes once per request.
 * The result (an empty array when nothing matches) is kept in the request attributes,
 * so the other kernel.request listeners reuse it instead of matching again.
 *
 * @property RouterInterface $router
 */
trait RpcRouteMatcherTrait
{
    public const string MATCHED_ROUTE_ATTR = '_ufo_rpc_route';

    protected function matchRoute(Request $request): array
    {
        if ($request->attributes->has(self::MATCHED_ROUTE_ATTR)) {
            return $request->attributes->get(self::MATCHED_ROUTE_ATTR);
        }

        // Execution is intercepted before Symfony reaches the GET controller.
        $context = $this->router->getContext();
        $previousMethod = $context->getMethod();
        $context->setMethod(Request::METHOD_GET);
        try {
            $route = $this->router->match($request->getPathInfo());
        } catch (RoutingException) {
            $route = [];
        } finally {
            $context->setMethod($previousMethod);
        }
        $request->attributes->set(self::MATCHED_ROUTE_ATTR, $route);

        return $route;
    }
}
