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
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;
use Throwable;
use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Factory\DefaultDTOTransformerFactory;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\ParamConvertors\IParamConvertor;
use Ufo\JsonRpcBundle\Server\ServiceMap\IServiceHolder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers\ChainServiceFiller;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers\IServiceFiller;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\UfoReflectionProcedure;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceTranslation;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Generator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\JsonSchemaNormalizer;
use Ufo\JsonRpcBundle\Validations\JsonSchema\JsonSchemaPropertyNormalizer;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcObject\Transformer\Transformer;
use Ufo\RpcObject\RPC\APIGroups;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;

use function array_diff_key;
use function array_flip;
use function array_keys;
use function array_merge;
use function array_search;
use function array_unique;
use function array_unshift;
use function class_exists;
use function enum_exists;
use function glob;
use function in_array;
use function interface_exists;
use function is_array;
use function is_string;
use function is_subclass_of;
use function preg_match;
use function usort;

class RpcServiceMapPass implements CompilerPassInterface
{
    protected ContainerBuilder $container;

    protected ?ChainServiceFiller $chainServiceFiller = null;
    protected ?SerializerInterface $serializer = null;

    protected const string DTO_FROM_ARRAY_TRANSFORMER_TAG = 'dto.from_array_transformer';

    /**
     * @var array<string, Service>
     */
    protected array $serviceMap = [];

    protected array $groupMaps = [];

    /**
     * @var array<string, object>
     */
    protected array $compiledServices = [];

    /**
     * @var array<string, array<string, array{title?: string, description?: string}>>
     */
    protected array $translations = [];

    protected string $defaultLocale = 'en';

    /**
     * @throws ReflectionException|RpcInternalException
     */
    public function process(ContainerBuilder $container): void
    {
        $this->container = $container;
        $this->compileStaticDTOTransformer();
        $this->defaultLocale = $container->hasParameter('kernel.default_locale')
            ? $container->getParameter('kernel.default_locale')
            : 'en';
        $projectDir = $container->getParameter('kernel.project_dir');
        $this->translations = $this->readTranslations($projectDir);
        // Rebuild the container (in debug) when RPC translation files are added, removed or changed.
        $container->fileExists($projectDir.'/translations', '/^rpc\.[a-zA-Z0-9_]+\.ya?ml$/');

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
        $maps = [];
        foreach (array_unique([APIGroups::DEFAULT_GROUP, ...array_keys($this->groupMaps)]) as $group) {
            $maps[$group] = $this->getServicesForMap($group);
        }
        $this->container->setParameter(IServiceHolder::GROUP_MAP, $maps);
        $this->container->setParameter(IServiceHolder::LOCALES, $this->getLocales());
        // Fingerprint of everything the documentation is built from: changes whenever methods or the map change.
        $this->container->setParameter(IServiceHolder::MAP_HASH, md5(serialize([
            $this->container->getParameter(IServiceHolder::MAP),
            $maps,
            $this->getLocales(),
        ])));
        DTOTransformer::reset();
    }

    /**
     * @throws RpcInternalException
     */
    protected function compileStaticDTOTransformer(): void
    {
        DTOTransformer::reset();
        $factory = DefaultDTOTransformerFactory::default();
//        $dtoTransformers = $this->getTaggedServices(self::DTO_FROM_ARRAY_TRANSFORMER_TAG);
        DTOTransformer::boot($factory->create(DTOTransformer::class));
    }

    /**
     * @throws RpcInternalException
     */
    public function getServicesForMap(string $group = APIGroups::DEFAULT_GROUP): array
    {
        $servicesData = [];
        $excludeByVersion = [];
        $versions = [];

        foreach (($this->groupMaps[$group] ?? ($group === APIGroups::DEFAULT_GROUP ? $this->serviceMap : [])) as $service) {
            $serviceName = $service->name;
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
        if (isset($this->compiledServices[$serviceId])) {
            return $this->compiledServices[$serviceId];
        }

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
        return $this->compiledServices[$serviceId] = $reflection->newInstanceArgs($params);
    }

    /**
     * Reads per-locale RPC translation files from `<projectDir>/translations/rpc.<locale>.yaml|yml`.
     *
     * @return array<string, array<string, array{title?: string, description?: string}>>
     */
    protected function readTranslations(string $projectDir): array
    {
        // GLOB_BRACE is a glibc extension, not guaranteed on every libc (e.g. musl/Alpine)
        // — two plain globs instead, merged.
        $files = [
            ...glob($projectDir.'/translations/rpc.*.yaml') ?: [],
            ...glob($projectDir.'/translations/rpc.*.yml') ?: [],
        ];

        $translations = [];
        foreach ($files as $file) {
            if (preg_match('/rpc\.([a-zA-Z0-9_]+)\.ya?ml$/', $file, $matches)) {
                $data = Yaml::parseFile($file) ?? [];
                $translations[$matches[1]] = is_array($data) ? $data : [];
            }
        }

        return $translations;
    }

    /**
     * Locales that have RPC translation files, the default locale (`kernel.default_locale`) first.
     *
     * @return string[]
     */
    protected function getLocales(): array
    {
        $locales = array_keys($this->translations);
        $defaultIndex = array_search($this->defaultLocale, $locales, true);
        if ($defaultIndex !== false) {
            unset($locales[$defaultIndex]);
            array_unshift($locales, $this->defaultLocale);
        }

        return $locales;
    }

    /**
     * Fills $service translations from the loaded translation files.
     * The default locale (`kernel.default_locale`), if present, is added first
     * so it becomes the fallback for locales that have no translation of their own.
     */
    protected function applyTranslations(Service $service): void
    {
        if (!$this->translations) {
            return;
        }

        foreach ($this->getLocales() as $locale) {
            $data = $this->translations[$locale][$service->name] ?? null;
            if (!is_array($data)) {
                continue;
            }

            $service->addTranslation(new ServiceTranslation(
                $locale,
                $data['title'] ?? $service->getTitle() ?? $service->name,
                $data['description'] ?? $service->getDescription(),
            ));
        }
    }

    public function processServiceMap(ReflectionClass $refClass): void
    {
        $reflection = new UfoReflectionProcedure(
            $refClass,
            $this->getServiceFiller()
        );
        $classGroups = $refClass->getAttributes(APIGroups::class)[0] ?? null;
        foreach ($reflection->getMethods() as $service) {
            $this->applyTranslations($service);
            $methodGroups = $refClass->getMethod($service->getMethodName())->getAttributes(APIGroups::class)[0] ?? null;
            $groups = ($methodGroups ?? $classGroups)?->newInstance() ?? new APIGroups();
            foreach ($groups->groups as $group) {
                $name = $service->name . ':' . $service->apiClassInfo->version;
                $existing = $this->groupMaps[$group][$name] ?? null;
                if ($existing && !is_subclass_of($service->getProcedureFQCN(), $existing->getProcedureFQCN())) {
                    if (is_subclass_of($existing->getProcedureFQCN(), $service->getProcedureFQCN())) {
                        continue;
                    }
                    throw new RuntimeException('Attempt to register a service "'.$service->name.'" already registered in API group "'.$group.'" detected');
                }
                $this->groupMaps[$group][$name] = $service;
                if ($group === APIGroups::DEFAULT_GROUP) {
                    $this->serviceMap[$name] = $service;
                }
            }
        }
    }

    protected function processParams(ReflectionMethod $method): array
    {
        $arguments = [];
        foreach ($method->getParameters() as $param) {

            $paramType = $param->getType();
            if (!$paramType || $paramType instanceof ReflectionUnionType) continue;

            $typeName = $paramType->getName();
            if (!$typeName || enum_exists($typeName)) continue;


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
