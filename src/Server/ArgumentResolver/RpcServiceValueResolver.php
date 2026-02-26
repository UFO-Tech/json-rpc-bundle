<?php

namespace Ufo\JsonRpcBundle\Server\ArgumentResolver;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Symfony-like service argument resolver for RPC methods.
 *
 * copy of Symfony\Component\HttpKernel\Controller\ArgumentResolver\ServiceValueResolver
 */
final class RpcServiceValueResolver
{
    /**
     * Locator map:
     *
     * [
     *   "UserRpc::get" => ServiceLocator(
     *        "factory" => UserFactory,
     *        "logger"  => LoggerInterface
     *   )
     * ]
     */
    public function __construct(
        private ContainerInterface $rpcArgumentLocators,
    ) {}

    /**
     * Resolve service argument.
     */
    public function resolve(
        string $rpcMethod,
        string $argumentName,
    ): mixed
    {
        if (!$this->rpcArgumentLocators->has($rpcMethod)) {
            return null;
        }

        $locator = $this->rpcArgumentLocators->get($rpcMethod);

        if (!$locator->has($argumentName)) {
            return null;
        }

        return $locator->get($argumentName);
    }
}