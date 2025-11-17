<?php

namespace Ufo\JsonRpcBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Ufo\JsonRpcBundle\ConfigService\RpcAsyncConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcCacheConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcDocsConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcSecurityConfig;
use Ufo\RpcObject\RPC\Cache;

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
    const string TREE_BUILDER_NAME = RpcMainConfig::NAME;

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
                ->arrayNode(RpcCacheConfig::NAME)->ignoreExtraKeys(false)
                    ->children()
                        ->integerNode(RpcCacheConfig::TTL)
                            ->defaultValue(Cache::T_MINUTE)
                        ->end()
                        ->scalarNode(RpcCacheConfig::PREFIX)
                            ->defaultValue(RpcCacheConfig::P_PREFIX)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode(RpcSecurityConfig::NAME)->ignoreExtraKeys(false)
                    ->children()
                        ->booleanNode(RpcSecurityConfig::PROTECTED_API)
                            ->defaultValue(true)
                        ->end()
                        ->booleanNode(RpcSecurityConfig::PROTECTED_DOC)
                            ->defaultValue(false)
                        ->end()
                        ->scalarNode(RpcSecurityConfig::TOKEN_NAME)
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
                ->arrayNode(RpcAsyncConfig::NAME)
                    ->beforeNormalization()
                        ->ifString()
                        ->then(fn($v) => [
                            RpcAsyncConfig::K_TYPE => RpcAsyncConfig::DEFAULT_TYPE,
                            RpcAsyncConfig::K_CONFIG => [
                                'name' => RpcAsyncConfig::RPC_ASYNC,
                                'dsn' => $v
                            ]
                        ])
                    ->end()
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(function ($v) {
                            // якщо це асоціативний масив без type → це скалярна форма з config
                            if (array_is_list($v)) return $v; // це список джерел вже

                            [$name, $dsn] = [array_key_first($v), reset($v)];
                            return [
                                [
                                    RpcAsyncConfig::K_TYPE => RpcAsyncConfig::DEFAULT_TYPE,
                                    RpcAsyncConfig::K_CONFIG => [
                                        'name' => $name,
                                        'dsn' => $dsn
                                    ]
                                ]
                            ];
                        })
                    ->end()
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode(RpcAsyncConfig::K_TYPE)
                                ->defaultValue('default')
                            ->end()
                            ->arrayNode(RpcAsyncConfig::K_CONFIG)
                                ->normalizeKeys(false)
                                ->variablePrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->ignoreExtraKeys(false)
        ;

        return $treeBuilder;
    }}
