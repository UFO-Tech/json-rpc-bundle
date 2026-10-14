<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\RpcObject\RPC\APIGroups;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Security\TokenHolders\HttpTokenHolder;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcTokenNotSentException;

use function in_array;

#[AsEventListener(KernelEvents::REQUEST, method: 'protectedApiDocumentation', priority: 240)]
class DocSecurityListener
{
    public function __construct(
        protected IRpcSecurity $rpcSecurity,
        protected RpcMainConfig $rpcConfig,
        protected RouterInterface $router,
        protected ?ApiGroupContext $apiGroupContext = null,
    ) {}

    /**
     * @throws RpcTokenNotSentException
     * @throws RpcInvalidTokenException
     */
    public function protectedApiDocumentation(RequestEvent $event): void
    {
        if (!$this->rpcConfig->securityConfig->protectedDoc) return;

        $request = $event->getRequest();

        if (!$request->isMethod(Request::METHOD_GET)) return;

        try {
            $route = $this->router instanceof RequestMatcherInterface
                ? $this->router->matchRequest($request)
                : $this->router->match($request->getPathInfo());
        } catch (RoutingException) {
            return;
        }

        if (!ApiController::isDocsRoute($route)) return;
        $this->apiGroupContext?->setGroup(
            $route[ApiGroupContext::ROUTE_GROUP] ?? APIGroups::DEFAULT_GROUP,
            $route[ApiGroupContext::ROUTE_NAME_PREFIX] ?? null
        );

        $this->rpcSecurity->setTokenHolder(new HttpTokenHolder($this->rpcConfig, $request));
        $this->rpcSecurity->isValidDocRequest();
    }
}
