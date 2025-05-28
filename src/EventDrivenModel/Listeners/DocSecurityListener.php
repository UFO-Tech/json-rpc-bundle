<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcTokenNotSentException;

use function in_array;

#[AsEventListener(KernelEvents::REQUEST, method: 'protectedApiDocumentation', priority: 100000)]
class DocSecurityListener
{
    public function __construct(
        protected IRpcSecurity $rpcSecurity,
        protected RpcMainConfig $rpcConfig,
        protected RouterInterface $router,
    ) {}

    /**
     * @throws RpcTokenNotSentException
     * @throws RpcInvalidTokenException
     */
    public function protectedApiDocumentation(RequestEvent $event): void
    {
        if (!$this->rpcConfig->securityConfig->protectedDoc) return;

        $request = $event->getRequest();
        $route = $this->router->match($request->getPathInfo());

        if (!in_array($route['_route'] ?? '', ApiController::API_DOC_ROUTES)) return;

        $this->rpcSecurity->isValidDocRequest();
    }
}
