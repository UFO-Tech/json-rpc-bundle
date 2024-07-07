<?php

namespace Ufo\JsonRpcBundle\ConfigService;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Yaml\Yaml;
use Ufo\JsonRpcBundle\DependencyInjection\Configuration;

use function parse_url;

final readonly class RpcMainConfig
{
    const string NAME = 'ufo_json_rpc';
    const string P_NAME = 'ufo_json_rpc_config';
    const string P_PROJECT_NAME = 'project_name';
    const string P_PROJECT_DESC = 'project_description';
    const string P_PROJECT_VER = 'project_version';
    const string P_PROJECT_NAME_DEFAULT = 'My Project';

    public string $projectName;

    public string $projectDesc;

    public ?string $projectVersion;

    public RpcSecurityConfig $securityConfig;

    public RpcDocsConfig $docsConfig;

    public RpcAsyncConfig $asyncConfig;

    public array $url;

    public function __construct(
        #[Target(self::P_NAME)]
        array $rpcConfigs,
        public string $environment,
        RequestStack $requestStack
    ) {
        $extraConfig = Yaml::parse(file_get_contents(__DIR__.'/../../install/packages/ufo_json_rpc.yaml'));
        $configs = $this->recursiveMerge($rpcConfigs, $extraConfig[self::NAME]);
        $configuration = new Configuration();
        $configs = $configuration->getConfigTreeBuilder()->buildTree()->normalize($configs);
        $this->projectName = $configs[self::P_PROJECT_NAME];
        $this->projectDesc = $configs[self::P_PROJECT_DESC];
        $this->projectVersion = $configs[self::P_PROJECT_VER];
        $this->securityConfig = new RpcSecurityConfig($configs[RpcSecurityConfig::NAME], $this);
        $this->docsConfig = new RpcDocsConfig($configs[RpcDocsConfig::NAME], $this);
        $this->asyncConfig = new RpcAsyncConfig($configs[RpcAsyncConfig::NAME], $this);
        $this->url = parse_url($requestStack->getCurrentRequest()?->getUri()) ?? [];
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