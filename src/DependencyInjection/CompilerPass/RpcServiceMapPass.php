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
use Ufo\JsonRpcBundle\ConfigService\RpcTranslationsConfig;
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
use function array_reduce;
use function array_search;
use function array_unique;
use function array_unshift;
use function array_values;
use function basename;
use function class_exists;
use function enum_exists;
use function glob;
use function in_array;
use function interface_exists;
use function is_array;
use function is_string;
use function is_subclass_of;
use function preg_match;
use function rtrim;
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

    protected const string BASE_TRANSLATION_FILE = '/^rpc\.([a-zA-Z0-9_]+)\.ya?ml$/';
    protected const string GROUP_TRANSLATION_FILE = '/^(.+)\.([a-zA-Z0-9_]+)\.ya?ml$/';

    /**
     * Base translations: [locale][method].
     *
     * @var array<string, array<string, array{title?: string, description?: string}>>
     */
    protected array $translations = [];

    /**
     * Overrides for an API group: [group][locale][method].
     *
     * @var array<string, array<string, array<string, array{title?: string, description?: string}>>>
     */
    protected array $groupTranslations = [];

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
        $this->translations = $this->groupTranslations = [];
        foreach ($this->translationDirs($container->getParameter('kernel.project_dir')) as $dir) {
            // Later directories override earlier ones field by field.
            $this->translations = $this->mergeTranslations($this->translations, $this->readTranslations($dir));
            foreach ($this->readGroupTranslations($dir) as $group => $translations) {
                $this->groupTranslations[$group] = $this->mergeTranslations($this->groupTranslations[$group] ?? [], $translations);
            }
            // Rebuild the container (in debug) when RPC translation files are added, removed or changed.
            $container->fileExists($dir, self::BASE_TRANSLATION_FILE);
            $container->fileExists($dir.'/rpc', self::GROUP_TRANSLATION_FILE);
        }

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
            $servicesData[$ver][$serviceName] = $this->translatedForGroup($service, $group)->toArray(false);
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
     * Directories with RPC translations: `ufo_json_rpc.translations.paths` (e.g. added by extensions via prepend()),
     * then the application's `translations/`, which goes last so it overrides the others.
     *
     * @return string[]
     */
    protected function translationDirs(string $projectDir): array
    {
        $paths = $this->container->hasParameter(RpcTranslationsConfig::PARAMETER)
            ? $this->container->getParameterBag()->resolveValue($this->container->getParameter(RpcTranslationsConfig::PARAMETER))
            : [];

        $dirs = [];
        foreach ([...$paths, $projectDir.'/translations'] as $path) {
            $path = rtrim($path, '/');
            unset($dirs[$path]); // a repeated directory takes its last position
            $dirs[$path] = $path;
        }

        return array_values($dirs);
    }

    /**
     * Reads base RPC translation files `<dir>/rpc.<locale>.yaml|yml`.
     *
     * @return array<string, array<string, array{title?: string, description?: string}>>
     */
    protected function readTranslations(string $dir): array
    {
        $translations = [];
        foreach ($this->readTranslationFiles($dir, self::BASE_TRANSLATION_FILE) as [$matches, $data]) {
            $translations[$matches[1]] = $data;
        }

        return $translations;
    }

    /**
     * Reads API group overrides `<dir>/rpc/<group>.<locale>.yaml|yml`.
     * The locale is the last segment before the extension, the group is everything before it.
     *
     * @return array<string, array<string, array<string, array{title?: string, description?: string}>>>
     */
    protected function readGroupTranslations(string $dir): array
    {
        $translations = [];
        foreach ($this->readTranslationFiles($dir.'/rpc', self::GROUP_TRANSLATION_FILE) as [$matches, $data]) {
            $translations[$matches[1]][$matches[2]] = $data;
        }

        return $translations;
    }

    /**
     * YAML files in $dir whose name matches $pattern, with the regex matches.
     *
     * @return iterable<array{0: string[], 1: array}>
     */
    protected function readTranslationFiles(string $dir, string $pattern): iterable
    {
        // GLOB_BRACE is a glibc extension, not guaranteed on every libc (e.g. musl/Alpine)
        // — two plain globs instead, merged.
        foreach ([...glob($dir.'/*.yaml') ?: [], ...glob($dir.'/*.yml') ?: []] as $file) {
            if (!preg_match($pattern, basename($file), $matches)) continue;
            $data = Yaml::parseFile($file) ?? [];
            yield [$matches, is_array($data) ? $data : []];
        }
    }

    /**
     * @return array<string, array<string, array{title?: string, description?: string}>>
     */
    protected function mergeTranslations(array $into, array $from): array
    {
        foreach ($from as $locale => $methods) {
            $into[$locale] ??= [];
            foreach ($methods as $method => $data) {
                if (!is_array($data)) continue;
                $into[$locale][$method] = [...($into[$locale][$method] ?? []), ...$data];
            }
        }

        return $into;
    }

    /**
     * Locales that have RPC translation files (base or group), the default locale (`kernel.default_locale`) first.
     *
     * @return string[]
     */
    protected function getLocales(): array
    {
        $locales = array_keys($this->translations);
        foreach ($this->groupTranslations as $translations) {
            $locales = [...$locales, ...array_keys($translations)];
        }
        $locales = array_values(array_unique($locales));
        $defaultIndex = array_search($this->defaultLocale, $locales, true);
        if ($defaultIndex !== false) {
            unset($locales[$defaultIndex]);
            array_unshift($locales, $this->defaultLocale);
        }

        return $locales;
    }

    /**
     * Fills $service with the base translations; it is shared by all API groups.
     * The default locale (`kernel.default_locale`), if present, is added first
     * so it becomes the fallback for locales that have no translation of their own.
     */
    protected function applyTranslations(Service $service): void
    {
        foreach ($this->resolveTranslations($service) as $translation) {
            $service->addTranslation($translation);
        }
    }

    /**
     * $service as it is serialized into the map of $group: with the group overrides, if the group has any.
     */
    protected function translatedForGroup(Service $service, string $group): Service
    {
        if (empty($this->groupTranslations[$group])) {
            return $service;
        }

        return $service->withTranslations(...$this->resolveTranslations($service, $group));
    }

    /**
     * Each field is looked up in order: group + locale, group + default locale, base + locale,
     * base + default locale, then the text from the attribute / PHPDoc. The group wins over the language.
     * A locale gets its own translation only if it has a file entry for the method (base or group);
     * other locales fall back to the first translation (the default locale).
     *
     * @return ServiceTranslation[]
     */
    protected function resolveTranslations(Service $service, ?string $group = null): array
    {
        $groupTranslations = $group === null ? [] : ($this->groupTranslations[$group] ?? []);
        $name = $service->name;
        $resolved = [];
        foreach ($this->getLocales() as $locale) {
            $own = [$groupTranslations[$locale][$name] ?? null, $this->translations[$locale][$name] ?? null];
            if (!is_array($own[0]) && !is_array($own[1])) {
                continue;
            }
            $steps = [
                $own[0],
                $groupTranslations[$this->defaultLocale][$name] ?? null,
                $own[1],
                $this->translations[$this->defaultLocale][$name] ?? null,
            ];
            $field = fn(string $key) => array_reduce(
                $steps,
                fn($found, ?array $step) => $found ?? $step[$key] ?? null
            );

            $resolved[] = new ServiceTranslation(
                $locale,
                $field('title') ?? $service->getTitle() ?? $name,
                $field('description') ?? $service->getDescription(),
            );
        }

        return $resolved;
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
