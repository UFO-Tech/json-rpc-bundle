<?php

namespace Ufo\JsonRpcBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Default TreeBuilder name
     */
    const TREE_BUILDER_NAME = 'ufo_json_rpc';

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder(self::TREE_BUILDER_NAME);
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('security')
                    ->children()
                        ->booleanNode('protected_get')
                            ->defaultFalse()
                        ->end()
                        ->booleanNode('protected_post')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('token_key_in_header')
                            ->defaultValue('Ufo-RPC-Token')
                        ->end()
                        ->arrayNode('clients_tokens')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
        return $treeBuilder;
    }
}
