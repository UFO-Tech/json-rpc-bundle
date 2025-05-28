<?php

namespace Ufo\JsonRpcBundle\ConfigService;

final readonly class RpcDocsConfig
{
    const string NAME = 'docs';
    const string RESPONSE = 'response';
    const string ASYNC_DSN_INFO = 'async_dsn_info';
    const string VALIDATIONS = 'validations';
    const string SYMFONY_ASSERTS = 'symfony_asserts';
    const string P_PROJECT_NAME = 'project_name';
    const string P_PROJECT_DESC = 'project_description';
    const string P_PROJECT_VER = 'project_version';
    const string P_PROJECT_NAME_DEFAULT = 'My Project';

    public string $keyForMethods;

    public bool $asyncDsnInfo;

    public bool $needJsonSchema;

    public bool $needSymfonyAsserts;

    public string $projectName;

    public string $projectDesc;

    public ?string $projectVersion;

    public function __construct(array $rpcConfigs, public RpcMainConfig $parent)
    {
        $this->keyForMethods = 'methods';
        $this->asyncDsnInfo = $rpcConfigs[self::ASYNC_DSN_INFO] ?? '';
        $this->needJsonSchema = true;
        $this->needSymfonyAsserts = $rpcConfigs[self::VALIDATIONS][self::SYMFONY_ASSERTS] ?? false;
        $this->projectName = $rpcConfigs[self::P_PROJECT_NAME] ?? 'rpc';
        $this->projectDesc = $rpcConfigs[self::P_PROJECT_DESC] ?? '';
        $this->projectVersion = $rpcConfigs[self::P_PROJECT_VER] ?? null;
    }

}