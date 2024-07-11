<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;

use Hub\Security\Authenticator\AccessTokenAuthenticator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Server\RpcRequestHelper;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcJsonParseException;
use Ufo\RpcError\RpcTokenNotFoundInHeaderException;
use Ufo\RpcObject\Events\RpcErrorEvent;
use Ufo\RpcObject\Events\RpcEvent;
use Ufo\RpcObject\RpcRequest;

use function in_array;

#[AsEventListener('kernel.request', method: 'protectedRequest', priority: 1000)]
class SecurityListener
{
    public function __construct(
        protected IRpcSecurity $rpcSecurity,
    ) {}

    /**
     * @throws RpcTokenNotFoundInHeaderException
     * @throws RpcInvalidTokenException
     */
    public function protectedRequest(RequestEvent $event): void
    {
        $this->rpcSecurity->isValidRequest();
    }

}
