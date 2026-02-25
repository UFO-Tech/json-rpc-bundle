<?php

namespace Ufo\JsonRpcBundle\DependencyInjection\CompilerPass;

use ReflectionAttribute;
use ReflectionUnionType;
use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Ufo\JsonRpcBundle\Server\ServiceMap\IServiceHolder;

use function class_exists;
use function enum_exists;
use function interface_exists;

final class RpcRegisterArgumentLocatorsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $rpcServices = $container->findTaggedServiceIds(IRpcService::TAG);

        $methodLocators = [];

        foreach ($rpcServices as $serviceId => $_tags) {

            $definition = $container->findDefinition($serviceId);

            $class = $definition->getClass();

            $refClass = $container->getReflectionClass($class, false);
            if (!$refClass) continue;

            foreach ($refClass->getMethods() as $method) {
                if ($method->isConstructor()) continue;
                $rpcMethodId = $class.'::'.$method->getName();

                $arguments = [];
                foreach ($method->getParameters() as $param) {

                    $paramType = $param->getType();
                    $typeName = $paramType?->getName() ?? null;

                    if (
                        !$paramType
                        || $paramType instanceof ReflectionUnionType
                        || !$typeName
                        || enum_exists($typeName)
                    ) continue;

                    $autowireAttr = $param->getAttributes(Autowire::class, ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
                    if (empty($autowireAttr)) continue;

                    try {
                        $autowire = $autowireAttr->newInstance();
                    } catch (LogicException $e) {
                        if (!class_exists($typeName) && !interface_exists($typeName)) throw $e;
                        $autowire = new Autowire(service: $typeName);
                    }
                    $parameterBag = $container->getParameterBag();

                    $arguments[$param->getName()] = match (true) {
                        $autowire->value instanceof Reference,
                        $autowire->value instanceof Expression,
                        $autowire->value instanceof ArgumentInterface
                            => $autowire->value,

                        default => $parameterBag->resolveValue($autowire->value),
                    };
                }

                if (empty($arguments)) continue;

                $locatorRef = ServiceLocatorTagPass::register(
                    $container,
                    $arguments
                );

                $methodLocators[$rpcMethodId] = $locatorRef;
            }
        }

        $locatorRef = ServiceLocatorTagPass::register(
            $container,
            $methodLocators
        );

        $container->setAlias(
            IServiceHolder::ARG_LOCATOR,
            (string) $locatorRef
        )->setPublic(false);
    }
}
