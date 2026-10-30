<?php

namespace Ufo\JsonRpcBundle\DependencyInjection;

use Exception;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Ufo\Component\TransportContracts\AsyncTransportConfigBuilderInterface;
use Ufo\Component\TransportContracts\AsyncTransportConfigBuilders;
use Ufo\Component\TransportContracts\AsyncTransportConfigDTO;
use Ufo\JsonRpcBundle\ConfigService\RpcAsyncConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcCacheConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\DependencyInjection\Async\ApiPairs;
use Ufo\JsonRpcBundle\DependencyInjection\Async\AsyncTransportName;
use Ufo\JsonRpcBundle\DependencyInjection\Attribute\RequiresExtension;
use Ufo\JsonRpcBundle\Routing\IApiRouteImport;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcObject\RpcAsyncRequest;

use function array_diff_key;
use function array_flip;
use function is_null;
use function ltrim;
use function sprintf;
use function trigger_deprecation;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class UfoJsonRpcExtension extends Extension implements PrependExtensionInterface
{
    protected const array ASYNC_SERVICE_KEYS = ['name', 'dsn', 'route'];

    /**
     * @var ?ContainerBuilder
     */
    protected ?ContainerBuilder $container = null;

    protected Configuration $config;

    public function __construct()
    {
        $this->config = new Configuration();
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        if (is_null($this->container)) {
            $this->container = $container;
        }
        $config = $this->processConfiguration($this->config, $configs);
        $this->container->setParameter($this->getAlias(), $config);
        $this->mapTreeToParams($config, $this->getAlias());

        $loader = new Loader\YamlFileLoader($this->container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');
        // Explicit: src/Routing/ is not scanned, so an attribute on the interface would never be read.
        $this->container->registerForAutoconfiguration(IApiRouteImport::class)->addTag(IApiRouteImport::TAG);
        $this->useCachePool($config[RpcCacheConfig::NAME][RpcCacheConfig::POOL] ?? null);
        $this->removeServicesWithMissingExtensions();
    }

    /**
     * `cache.pool` replaces the default filesystem pool (local to each server) with any cache pool service,
     * e.g. a shared Redis pool. `ttl` and `prefix` apply only to the default pool.
     */
    protected function useCachePool(?string $pool): void
    {
        if (!$pool) return;
        $this->container->setAlias(RpcCacheConfig::POOL_SERVICE, ltrim($pool, '@'));
    }

    public function prepend(ContainerBuilder $container): void
    {
        if (is_null($this->container)) {
            $this->container = $container;
        }
        $configs = $this->container->getExtensionConfig($this->getConfigName());
        $config = $this->processConfiguration($this->config, $configs);
        $this->mapAsyncTransportParams($config[RpcAsyncConfig::NAME] ?? []);
    }

    /**
     * Транспорт Messenger на кожну пару (група, версія) для кожного запису async, що має адаптер свого типу.
     *
     * @param array{type:string, config:array<string,mixed>} $asyncConfigs
     * @return void
     * @throws RpcBadParamException
     */
    protected function mapAsyncTransportParams(array $asyncConfigs): void
    {
        $builders = $this->getAsyncConfigBuilders();
        $pairs = null;
        $transports = $routs = $queues = [];
        foreach ($asyncConfigs as $asyncConfig) {
            $type = $asyncConfig[RpcAsyncConfig::K_TYPE] ?? throw new RpcBadParamException('Invalid async transport config. Async transport type is not set');
            $builder = $builders[$type] ?? null;
            $config = $asyncConfig[RpcAsyncConfig::K_CONFIG] ?? [];
            if ($type === RpcAsyncConfig::DEFAULT_TYPE) {
                $this->mapLegacyAsyncTransport($config, $builder !== null, $transports, $routs);
            }
            if (!$builder) continue;

            $baseName = $config['name'] ?? RpcAsyncConfig::RPC_ASYNC;
            $dsn = $config['dsn'] ?? throw new RpcBadParamException(sprintf('Invalid async transport config. Async transport "%s" has no dsn', $baseName));
            $options = array_diff_key($config, array_flip(static::ASYNC_SERVICE_KEYS));
            $pairs ??= ApiPairs::fromContainer($this->container);

            foreach ($pairs as [$group, $version]) {
                $name = AsyncTransportName::expand($baseName, $group, $version);
                $queue = $builder->getQueueName($name);
                if (isset($queues[$queue]) || isset($transports[$name])) {
                    throw new RpcBadParamException(sprintf('Async transports "%s" and "%s" use the same name or queue "%s"', $queues[$queue] ?? $name, $name, $queue));
                }
                $queues[$queue] = $name;
                $transport = $builder->build(new AsyncTransportConfigDTO($name, $dsn, $options));
                $transports[$name] = ['dsn' => $transport->dsn, 'options' => $transport->options];
                if (isset($config['route']) && $type !== RpcAsyncConfig::DEFAULT_TYPE) {
                    $routs[$config['route']][] = $name;
                }
            }
        }

        if (!empty($transports)) {
            $this->container->prependExtensionConfig('framework', [
                'messenger' => [
                    'transports' => $transports,
                    'routing'    => $routs,
                ],
            ]);
        }
    }

    /**
     * Транспорт amqp, як його реєструвала 10.4: ім'я з config.name, DSN як є, маршрут RpcAsyncRequest.
     *
     * @throws RpcBadParamException
     */
    protected function mapLegacyAsyncTransport(array $config, bool $hasBuilder, array &$transports, array &$routs): void
    {
        $name = $config['name'] ?? RpcAsyncRequest::class;
        if (AsyncTransportName::isTemplate($name)) {
            return;
        }
        $transports[$name] = $config['dsn'] ?? throw new RpcBadParamException(sprintf('Invalid async transport config. Async transport "%s" has no dsn', $name));
        $routs[$config['route'] ?? RpcAsyncRequest::class] = [$name];
        if (!$hasBuilder && !$this->isProdEnvironment()) {
            trigger_deprecation('ufo-tech/json-rpc-bundle', '10.5', 'Async transport "%s" gets no queue per API group and version: install "ufo-tech/component-transport-amqp".', $name);
        }
    }

    protected function isProdEnvironment(): bool
    {
        return $this->container->hasParameter('kernel.environment')
            && $this->container->getParameter('kernel.environment') === 'prod';
    }

    protected function removeServicesWithMissingExtensions(): void
    {
        foreach ($this->container->getDefinitions() as $serviceId => $definition) {
            $class = $definition->getClass();
            if (!$class) continue;

            $reflection = $this->container->getReflectionClass($class, false);
            if (!$reflection) continue;

            foreach ($reflection->getAttributes(RequiresExtension::class) as $attribute) {
                $requiredExtension = $attribute->newInstance()->extension;
                if (!$this->container->hasExtension($requiredExtension)) {
                    $this->container->removeDefinition($serviceId);
                    break;
                }
            }
        }
    }

    protected function mapTreeToParams(array $paramsArray, string $paramKey): void
    {
        foreach ($paramsArray as $key => $value) {
            $newKey = $paramKey.'.'.$key;
            $this->container->setParameter($newKey, $value);
            if (is_array($value)) {
                $this->mapTreeToParams($value, $newKey);
            }
        }
    }

    /**
     * @return array<string, AsyncTransportConfigBuilderInterface> ключ — тип транспорту
     */
    protected function getAsyncConfigBuilders(): array
    {
        return AsyncTransportConfigBuilders::discover();
    }

    public function getAlias(): string
    {
        return $this->getConfigName();
    }

    protected function getConfigName(): string
    {
        return RpcMainConfig::NAME;
    }
}
