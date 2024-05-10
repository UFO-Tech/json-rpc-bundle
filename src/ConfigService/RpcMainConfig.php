<?php

namespace Ufo\JsonRpcBundle\ConfigService;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;
use Ufo\JsonRpcBundle\DependencyInjection\Configuration;

final readonly class RpcMainConfig
{
    const NAME = 'ufo_json_rpc';
    const P_NAME = 'ufo_json_rpc_config';
    public RpcSecurityConfig $securityConfig;
    public RpcDocsConfig $docsConfig;

    public function __construct(
        #[Target(self::P_NAME)]
        array $rpcConfigs,
        public string $environment
    ) {
        $extraConfig = Yaml::parse(file_get_contents(__DIR__.'/../../install/packages/ufo_json_rpc.yaml'));
        $configs = $this->recursiveMerge($rpcConfigs, $extraConfig[self::NAME]);
        $configuration = new Configuration();
        $configs = $configuration->getConfigTreeBuilder()->buildTree()->normalize($configs);
        $this->securityConfig = new RpcSecurityConfig($configs[RpcSecurityConfig::NAME]);
        $this->docsConfig = new RpcDocsConfig($configs[RpcDocsConfig::NAME]);
    }

    protected function recursiveMerge(array $config, array $extraConfig): array
    {
        foreach ($extraConfig as $key => $value) {
            if (array_key_exists($key, $config)) {
                if (is_array($value) && is_array($config[$key])) {
                    // Якщо обидва значення є масивами, перевіряємо, чи є масив асоціативним
                    if (array_keys($value) !== range(0, count($value) - 1)) {
                        // Асоціативний масив, мерджимо рекурсивно
                        $config[$key] = $this->recursiveMerge($config[$key], $value);
                    }
                }
            } else {
                $config[$key] = $value;
            }
        }

        return $config;
    }
}