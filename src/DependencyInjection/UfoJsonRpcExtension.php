<?php

namespace Ufo\JsonRpcBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Ufo\JsonRpcBundle\ConfigService\RpcAsyncConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\RpcObject\RpcAsyncRequest;

use function array_merge_recursive;
use function is_null;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class UfoJsonRpcExtension extends Extension implements PrependExtensionInterface
{
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
        $configs = $this->container->getExtensionConfig($this->getAlias());
        $config = $this->processConfiguration($this->config, $configs);
        $this->mapAsyncTransportParams($config[RpcAsyncConfig::NAME] ?? []);
    }

    protected function mapAsyncTransportParams(array $asyncConfig): void
    {
        $transports = [];
        if (!empty($asyncConfig[RpcAsyncConfig::RPC_ASYNC])) {
            $transports[RpcAsyncConfig::RPC_ASYNC] = $asyncConfig[RpcAsyncConfig::RPC_ASYNC];
        }
        if (!empty($asyncConfig[RpcAsyncConfig::FAILED])) {
            $transports[RpcAsyncConfig::FAILED] = $asyncConfig[RpcAsyncConfig::FAILED];
        }
        if (!empty($transports)) {
            $messengerConfig = [
                'transports' => $transports,
                'routing'    => [
                    RpcAsyncRequest::class => RpcAsyncConfig::RPC_ASYNC,
                ],
            ];
            $this->container->prependExtensionConfig('framework', [
                'messenger' => $messengerConfig,
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

    public function getAlias(): string
    {
        return RpcMainConfig::NAME;
    }

}
