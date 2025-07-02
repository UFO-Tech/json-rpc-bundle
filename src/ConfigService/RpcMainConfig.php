<?php

namespace Ufo\JsonRpcBundle\ConfigService;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Yaml\Yaml;
use Ufo\JsonRpcBundle\DependencyInjection\Configuration;

use function array_column;
use function array_map;
use function array_reduce;
use function parse_url;

final readonly class RpcMainConfig
{
    const string NAME = 'ufo_json_rpc';
    const string SDK_NAME = 'json_rpc_sdk';
    const string P_NAME = 'ufo_json_rpc_config';

    public RpcSecurityConfig $securityConfig;

    public RpcDocsConfig $docsConfig;

    public RpcAsyncConfig $asyncConfig;

    public array $url;

    public array $sdkVendors;

    public function __construct(
        array $rpcConfigs,
        public string $environment,
        RequestStack $requestStack,
        array $sdkConfigs,
    ) {
        $extraConfig = Yaml::parse(file_get_contents(__DIR__.'/../../install/packages/ufo_json_rpc.yaml'));
        $configs = $this->recursiveMerge($rpcConfigs, $extraConfig[self::NAME]);
        $configuration = new Configuration();
        $configs = $configuration->getConfigTreeBuilder()->buildTree()->normalize($configs);
        $this->securityConfig = new RpcSecurityConfig($configs[RpcSecurityConfig::NAME], $this);
        $this->docsConfig = new RpcDocsConfig($configs[RpcDocsConfig::NAME], $this);
        $this->asyncConfig = new RpcAsyncConfig($configs[RpcAsyncConfig::NAME], $this);
        $url = $requestStack->getCurrentRequest()?->getUri() ?? "";
        $this->url = parse_url($url) ?? [];

        $this->sdkVendors = array_column($sdkConfigs['vendors'] ?? [], 'name');;
    }

    protected function recursiveMerge(array $config, array $extraConfig): array
    {
        foreach ($extraConfig as $key => $value) {
            if (array_key_exists($key, $config)) {
                if (is_array($value) && is_array($config[$key])) {
                    if (array_keys($value) !== range(0, count($value) - 1)) {
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