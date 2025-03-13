<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcTokenNotFoundInHeaderException;

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
