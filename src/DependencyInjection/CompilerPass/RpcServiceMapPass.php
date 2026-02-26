<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\DependencyInjection\CompilerPass;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionObject;
use ReflectionUnionType;
use RuntimeException;
use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\ParamConvertors\IParamConvertor;
use Ufo\JsonRpcBundle\Server\ServiceMap\IServiceHolder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers\ChainServiceFiller;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers\IServiceFiller;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\UfoReflectionProcedure;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Generator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\JsonSchemaNormalizer;
use Ufo\JsonRpcBundle\Validations\JsonSchema\JsonSchemaPropertyNormalizer;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcObject\Transformer\Transformer;

use function array_diff_key;
use function array_flip;
use function array_keys;
use function array_merge;
use function array_unique;
use function class_exists;
use function enum_exists;
use function in_array;
use function interface_exists;
use function is_string;
use function is_subclass_of;
use function usort;

class RpcServiceMapPass implements CompilerPassInterface
{
    protected ContainerBuilder $container;

    protected ?ChainServiceFiller $chainServiceFiller = null;
    protected ?SerializerInterface $serializer = null;

    /**
     * @var array<string, Service>
     */
    protected array $serviceMap = [];

    /**
     * @throws ReflectionException|RpcInternalException
     */
    public function process(ContainerBuilder $container): void
    {
        $this->container = $container;
        $rpcServices = $container->findTaggedServiceIds(IRpcService::TAG);
        $rpcClassRef = []; // ServiceLocator
        $methodLocators = []; // MethodLocators
        foreach ($rpcServices as $serviceId => $_tags) {
            $rpcClassRef[$serviceId] = new Reference($serviceId);

            $definition = $container->findDefinition($serviceId);
            $class = $definition->getClass();
            $refClass = $container->getReflectionClass($class, false);
            if (!$refClass) continue;

            foreach ($refClass->getMethods() as $method) {
                if ($method->isConstructor() || !$method->isPublic() || $method->isStatic()) continue;
                $classMethodId = $class.'::'.$method->getName();

                $methodLocators[$classMethodId] = ServiceLocatorTagPass::register(
                    $container,
                    $this->processParams($method)
                );

            }
            $this->processServiceMap($refClass);
        }

        $this->registration(IServiceHolder::LOCATOR, $rpcClassRef);
        $this->registration(IServiceHolder::ARG_LOCATOR, $methodLocators);
        $this->container->setParameter(IServiceHolder::MAP, $this->getServicesForMap());
    }

    /**
     * @throws RpcInternalException
     */
    public function getServicesForMap(): array
    {
        $servicesData = [];
        $excludeByVersion = [];
        $versions = [];

        foreach ($this->serviceMap as $service) {
            $serviceName = $service->getName();
            $ver = $service->apiClassInfo->version;
            $versions[$ver] = $ver;
            $servicesData[$ver][$serviceName] = $service->toArray(false);
            $excludeByVersion[$ver] = array_unique(array_merge(
                $excludeByVersion[$ver] ?? [],
                $service->apiClassInfo->removedMethods
            ));
        }

        usort($versions, 'version_compare');

        $result = [];
        $prev = [];

        foreach ($versions as $ver) {
            $current = $servicesData[$ver] ?? [];

            foreach ($prev as $name => $service) {
                $current[$name] ??= $service;
            }

            $current = array_diff_key(
                $current,
                array_flip($excludeByVersion[$ver] ?? [])
            );

            $result[$ver] = $current;
            $prev = $current;
        }

        return $result;
    }


    protected function getServiceFiller(): ChainServiceFiller
    {
        if (!$this->chainServiceFiller) {
            $this->chainServiceFiller = new ChainServiceFiller($this->getTaggedServices(IServiceFiller::TAG));
        }
        return $this->chainServiceFiller;
    }

    protected function compileSerializer(): SerializerInterface
    {
        if (!$this->serializer) {
            $s = Transformer::getDefault();
            $refS = new ReflectionObject($s);
            $propNormalizers = $refS->getProperty('normalizers')->getValue($s);

            $generator = new Generator($this->getTaggedServices(IConstraintGenerator::TAG));
            $propNormalizer = new JsonSchemaPropertyNormalizer($generator);

            $paramConvertor = new ChainParamConvertor($propNormalizer, $this->getTaggedServices(IParamConvertor::TAG));

            $propNormalizers = [
                new JsonSchemaNormalizer($propNormalizer, $paramConvertor),
                $propNormalizer,
                ...$propNormalizers,
            ];
            $refS->getProperty('normalizers')->setValue($s, $propNormalizers);
            $this->serializer = $s;
        }
        return $this->serializer;
    }

    protected function getTaggedServices(string $tag): array
    {
        $proto = $this->container->findTaggedServiceIds($tag);

        uasort(
            $proto,
            static fn(array $a, array $b): int => (($b[0]['priority'] ?? 0) <=> ($a[0]['priority'] ?? 0))
        );

        $services = [];
        foreach (array_keys($proto) as $serviceId) {
            $services[] = $this->getReferenceService($serviceId);
        }

        return $services;
    }

    protected function getReferenceService(string $serviceId, int $deep = 0, bool $allowNull = false): ?object
    {
        if ($serviceId === SerializerInterface::class) return $this->compileSerializer();
        // In compiler passes, interfaces are usually registered as aliases (autowiring). `findDefinition()` resolves aliases.
        if (!$this->container->has($serviceId) && !$this->container->hasAlias($serviceId) && !$this->container->hasDefinition($serviceId)) {
            throw new RpcInternalException('Service "' . $serviceId . '" not found');
        }

        $definition = $this->container->findDefinition($serviceId);

        $class = $definition->getClass();
        if (!$class) {
            $factory = $definition->getFactory();
            $factory0 = $factory[0] ?? null;

            if ($factory0 instanceof Reference) {
                $factoryDef = $this->container->findDefinition((string) $factory0);
                $class = $factoryDef->getClass();
            } elseif (is_string($factory0)) {
                $class = $factory0;
            }

            if (!$class) {
                if ($allowNull) return null;
                throw new RpcInternalException('Cannot resolve class for service "' . $serviceId . '"');
            }
        }

        $reflection = $this->container->getReflectionClass($class, false)
                      ?? throw new RpcInternalException('Reflection for service "' . $serviceId . '" not found');

        $ctor = $reflection->getConstructor();
        if (!$ctor) {
            return $reflection->newInstance();
        }

        $params = [];
        foreach ($ctor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof \ReflectionNamedType) {
                throw new RpcInternalException(
                    'Cannot autowire parameter $' . $parameter->getName() . ' of service "' . $serviceId . '"'
                );
            }

            // Support iterable/array constructor arguments (e.g. AutowireIterator)
            if ($type->isBuiltin()) {
                if (in_array($type->getName(), ['array', 'iterable'], true)) {
                    // If this is an #[AutowireIterator] parameter, resolve tagged services now
                    $iterAttr = $parameter->getAttributes(AutowireIterator::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
                    if ($iterAttr) {
                        $iter = $iterAttr->newInstance();

                        // AutowireIterator provides a tag name; at compile-time we can expand it via findTaggedServiceIds()
                        $tag = $iter->tag ?? null;
                        if (is_string($tag) && $tag !== '') {
                            $tagged = $this->container->findTaggedServiceIds($tag);

                            $items = [];
                            foreach (array_keys($tagged) as $taggedId) {
                                // Keep recursion bounded just in case (circular deps)
                                if ($deep > 25) {
                                    throw new RpcInternalException('Dependency graph too deep while resolving AutowireIterator for service "' . $serviceId . '"');
                                }

                                $items[] = $this->getReferenceService($taggedId, $deep + 1);
                            }

                            $params[] = $items;
                            continue;
                        }
                    }

                    // Fallback: empty collection
                    $params[] = [];
                    continue;
                }

                throw new RpcInternalException(
                    'Cannot autowire builtin parameter $' . $parameter->getName() . ' of service "' . $serviceId . '"'
                );
            }

            $depId = $type->getName();

            // NOTE: This still instantiates services at compile time, which is generally unsafe.
            // This change only fixes alias/interface resolution (e.g. SerializerInterface).
            try {
                $params[] = $this->container->get($depId);
            } catch (Throwable) {
                $params[] = $this->getReferenceService($depId, $deep + 1, $type->allowsNull());
            }
        }

        return $reflection->newInstanceArgs($params);
    }

    public function processServiceMap(ReflectionClass $refClass): void
    {
        $reflection = new UfoReflectionProcedure(
            $refClass,
            $this->getServiceFiller()
        );
        foreach ($reflection->getMethods() as $service) {
            $name = $service->getName() . ':' . $service->apiClassInfo->version;
            if (($this->serviceMap[$name] ?? false)
                && !is_subclass_of($service->getProcedureFQCN(), $this->serviceMap[$name]->getProcedureFQCN())
            ) {
                if (is_subclass_of($this->serviceMap[$name]->getProcedureFQCN(), $service->getProcedureFQCN())) {
                    continue;
                } else {
                    throw new RuntimeException('Attempt to register a service "'. $service->getName() .'" already registered detected');

                }
            }
            $this->serviceMap[$name] = $service;
        }
    }

    protected function processParams(ReflectionMethod $method): array
    {
        $arguments = [];
        foreach ($method->getParameters() as $param) {

            $paramType = $param->getType();
            if (!$paramType || $paramType instanceof ReflectionUnionType) continue;

            $typeName = $paramType->getName();
            if ($typeName || enum_exists($typeName)) continue;


            $autowireAttr = $param->getAttributes(Autowire::class, ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
            if (empty($autowireAttr)) continue;

            try {
                $autowire = $autowireAttr->newInstance();
            } catch (LogicException $e) {
                if (!class_exists($typeName) && !interface_exists($typeName)) throw $e;
                $autowire = new Autowire(service: $typeName);
            }
            $parameterBag = $this->container->getParameterBag();

            $arguments[$param->getName()] = match (true) {
                $autowire->value instanceof Reference,
                    $autowire->value instanceof Expression,
                    $autowire->value instanceof ArgumentInterface
                => $autowire->value,

                default => $parameterBag->resolveValue($autowire->value),
            };

        }
        return $arguments;
    }

    public function registration(string $name,  array $refs): void
    {
        $this->container->setAlias(
            $name,
            (string)ServiceLocatorTagPass::register($this->container, $refs)
        )->setPublic(false);
    }

}
