<?php

namespace Ufo\JsonRpcBundle\DependencyInjection;

use Exception;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Ufo\JsonRpcBundle\ConfigService\RpcAsyncConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcCacheConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcObject\RPC\Cache;
use Ufo\RpcObject\RpcAsyncRequest;

use function is_null;
use function ltrim;
use function substr;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class UfoJsonRpcExtension extends Extension implements PrependExtensionInterface
{
    public const array ALLOWED_MESSENGER_PROTOCOLS = [
        RpcAsyncConfig::DEFAULT_TYPE
    ];

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
     * @param array{type:string, config:array<string,string>} $asyncConfigs
     * @return void
     * @throws RpcBadParamException
     */
    protected function mapAsyncTransportParams(array $asyncConfigs): void
    {
        $transports = $routs = [];
        foreach ($asyncConfigs as $asyncConfig) {
            $protocol = $asyncConfig[RpcAsyncConfig::K_TYPE] ?? throw new RpcBadParamException('Invalid async transport config. Async transport type is not set');
            if (!in_array($protocol, static::ALLOWED_MESSENGER_PROTOCOLS)) continue;
            $config = $asyncConfig[RpcAsyncConfig::K_CONFIG];
            $route = $config['route'] ?? $this->getRoute($asyncConfig[RpcAsyncConfig::K_TYPE]);
            $name = $config['name'] ?? RpcAsyncRequest::class;
            $transports[$name] = $config['dsn'];
            $routs[$route] = $name;
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

    protected function getRoute(string $type): string
    {
        return $this->getRouteMapper()[$type] ?? RpcAsyncRequest::class;
    }

    protected function getRouteMapper(): array
    {
        return [
            'amqp'=> RpcAsyncRequest::class
        ];
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
