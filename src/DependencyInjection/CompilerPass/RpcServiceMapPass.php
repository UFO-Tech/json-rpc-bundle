<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\DependencyInjection\CompilerPass;

use ReflectionAttribute;
use ReflectionUnionType;
use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\Server\ServiceMap\IServiceHolder;


class RpcServiceMapPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $rpcServices = $container->findTaggedServiceIds(IRpcService::TAG);
        $rpcClassRef = [];
        foreach ($rpcServices as $serviceId => $_tags) {
            $rpcClassRef[$serviceId] = new Reference($serviceId);

            $definition = $container->findDefinition($serviceId);
            $class = $definition->getClass();

            $refClass = $container->getReflectionClass($class, false);
            if (!$refClass) continue;

            foreach ($refClass->getMethods() as $method) {
                if ($method->isConstructor()) continue;

                $rpcMethodId = $class.'::'.$method->getName();

                $arguments = [
                    // Reference
                ];

//                $locatorRef = ServiceLocatorTagPass::register(
//                    $container,
//                    $arguments
//                );

//                $services[$rpcMethodId] = $locatorRef;
            }
        }

        $container
            ->setAlias(
                IServiceHolder::LOCATOR,
                (string) ServiceLocatorTagPass::register(
                    $container,
                    $rpcClassRef
                )
            )->setPublic(false)
        ;
    }

}
