<?php

namespace Ufo\JsonRpcBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Ufo\JsonRpcBundle\ConfigService\RpcAsyncConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcDocsConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcSecurityConfig;

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
    const TREE_BUILDER_NAME = RpcMainConfig::NAME;

    /**
     * {@inheritdoc}
     * @formatter:off
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(RpcMainConfig::NAME);
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode(RpcSecurityConfig::NAME)->ignoreExtraKeys(false)
                    ->children()
                        ->booleanNode(RpcSecurityConfig::PROTECTED_API)
                            ->defaultValue(true)
                        ->end()
                        ->booleanNode(RpcSecurityConfig::PROTECTED_DOC)
                            ->defaultValue(false)
                        ->end()
                        ->scalarNode(RpcSecurityConfig::TOKEN_KEY)
                            ->defaultValue(RpcSecurityConfig::DEFAULT_TOKEN_KEY)
                        ->end()
                        ->arrayNode(RpcSecurityConfig::TOKENS)
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode(RpcDocsConfig::NAME)->ignoreExtraKeys(false)
                    ->children()
                        ->scalarNode(RpcDocsConfig::P_PROJECT_NAME)
                            ->defaultValue(RpcDocsConfig::P_PROJECT_NAME_DEFAULT)
                        ->end()
                        ->scalarNode(RpcDocsConfig::P_PROJECT_DESC)
                            ->defaultValue("")
                        ->end()
                        ->scalarNode(RpcDocsConfig::P_PROJECT_VER)
                            ->defaultValue(null)
                        ->end()

                        ->booleanNode(RpcDocsConfig::ASYNC_DSN_INFO)
                            ->defaultFalse()
                        ->end()
                        ->arrayNode(RpcDocsConfig::VALIDATIONS)
                            ->children()
                                ->booleanNode(RpcDocsConfig::SYMFONY_ASSERTS)
                                    ->defaultFalse()
                                ->end()
                            ->end()
                        ->end()

                    ->end()
                ->end()
                ->arrayNode(RpcAsyncConfig::NAME)->ignoreExtraKeys(false)
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode(RpcAsyncConfig::RPC_ASYNC)
                            ->defaultNull()
                        ->end()
                        ->scalarNode(RpcAsyncConfig::FAILED)
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->ignoreExtraKeys(false)
        ;

        return $treeBuilder;
    }}
