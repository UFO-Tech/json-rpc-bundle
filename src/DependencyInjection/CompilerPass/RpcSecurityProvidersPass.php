<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\DependencyInjection\CompilerPass;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator;
use Ufo\JsonRpcBundle\Security\SimpleTokenValidator;
use Ufo\JsonRpcBundle\Security\TokenValidatorRegistry;
use Ufo\JsonRpcBundle\ConfigService\RpcSecurityProviderConfig;

class RpcSecurityProvidersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Group providers must not make autowiring the base validator ambiguous.
        // Preserve explicit application aliases and definitions for the interface.
        if (!$container->has(ITokenValidator::class)) {
            $container->setAlias(ITokenValidator::class, SimpleTokenValidator::class);
        }
        $validators = [];
        foreach ($container->getParameter(RpcSecurityProviderConfig::PARAMETER) as $name => $config) {
            $provider = new RpcSecurityProviderConfig((string)$name, $config);
            $id = $provider->service;
            if (!$container->has($id)) {
                throw new InvalidArgumentException('RPC token provider "'.$name.'" references unknown service "'.$id.'".');
            }
            $class = $container->findDefinition($id)->getClass();
            $reflection = $class ? $container->getReflectionClass($class) : null;
            if (!$reflection?->implementsInterface(ITokenValidator::class)) {
                throw new InvalidArgumentException('RPC token provider "'.$name.'" must implement '.ITokenValidator::class.'.');
            }
            foreach ($provider->apiGroups as $group) {
                if (isset($validators[$group])) {
                    throw new InvalidArgumentException('Multiple RPC token providers configured for API group "'.$group.'".');
                }
                $validators[$group] = new Reference($id);
            }
        }
        $container->getDefinition(TokenValidatorRegistry::class)->setArgument('$validators', ServiceLocatorTagPass::register($container, $validators));
    }
}
