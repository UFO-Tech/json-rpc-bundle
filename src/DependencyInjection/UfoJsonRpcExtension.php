<?php

namespace Ufo\JsonRpcBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class UfoJsonRpcExtension extends Extension
{
    /**
     * @var ContainerBuilder
     */
    protected $container;

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $this->container = $container;
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $this->container->setParameter('ufo_json_rpc', $config);

        $this->mapTreeToParams($config, 'ufo_json_rpc');

        $loader = new Loader\YamlFileLoader($this->container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yml');
    }

    protected function mapTreeToParams(array $paramsArray, string $paramKey)
    {
        foreach ($paramsArray as $key => $value) {
            $newKey = $paramKey . '.' . $key;
            $this->container->setParameter($newKey, $value);
            if (is_array($value)) {
                $this->mapTreeToParams($value, $newKey);
            }
        }
    }
}
