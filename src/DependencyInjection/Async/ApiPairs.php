<?php

namespace Ufo\JsonRpcBundle\DependencyInjection\Async;

use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\RpcObject\RPC\APIGroups;
use Ufo\RpcObject\RPC\IgnoreApi;
use Ufo\RpcObject\RPC\Info;

use function array_values;
use function is_string;
use function str_starts_with;

/**
 * Пари (група, версія) RPC-сервісів, видимі в контейнері до компіляції.
 * Пара general/v1 є завжди: її дає PingProcedure бандла, зареєстрований пізніше.
 */
final class ApiPairs
{
    /**
     * @return list<array{string, string}>
     */
    public static function fromContainer(ContainerBuilder $container): array
    {
        $pairs = [APIGroups::DEFAULT_GROUP.'.'.Info::DEFAULT_VERSION => [APIGroups::DEFAULT_GROUP, Info::DEFAULT_VERSION]];
        foreach ($container->getDefinitions() as $definition) {
            if ($definition->isAbstract() || $definition->isSynthetic()) continue;
            $class = $container->getParameterBag()->resolveValue($definition->getClass());
            if (!is_string($class)) continue;
            $refClass = $container->getReflectionClass($class, false);
            if (!$refClass || $refClass->isAbstract() || !$refClass->implementsInterface(IRpcService::class)) continue;

            foreach (self::ofClass($refClass) as [$group, $version]) {
                $pairs[$group.'.'.$version] = [$group, $version];
            }
        }

        return array_values($pairs);
    }

    /**
     * @return list<array{string, string}>
     */
    public static function ofClass(ReflectionClass $refClass): array
    {
        $version = ($refClass->getAttributes(Info::class)[0] ?? null)?->newInstance()->version ?? Info::DEFAULT_VERSION;
        $classGroups = $refClass->getAttributes(APIGroups::class)[0] ?? null;
        $pairs = [];
        foreach ($refClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || str_starts_with($method->getName(), '__') || $method->getAttributes(IgnoreApi::class)) continue;

            $methodGroups = $method->getAttributes(APIGroups::class)[0] ?? null;
            foreach ((($methodGroups ?? $classGroups)?->newInstance() ?? new APIGroups())->groups as $group) {
                $pairs[$group] = [ApiGroupContext::validateGroup($group), $version];
            }
        }

        return array_values($pairs);
    }
}
