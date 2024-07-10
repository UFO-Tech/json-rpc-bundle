<?php

namespace Ufo\JsonRpcBundle\ConfigService;

final readonly class RpcDocsConfig
{
    const string NAME = 'docs';
    const string RESPONSE = 'response';
    const string KEY_FOR_METHODS = 'key_for_methods';
    const string ASYNC_DSN_INFO = 'async_dsn_info';
    const string VALIDATIONS = 'validations';
    const string JSON_SCHEMA = 'json_schema';
    const string SYMFONY_ASSERTS = 'symfony_asserts';
    const string DEFAULT_KEY_FOR_METHODS = 'services';

    public string $keyForMethods;

    public bool $asyncDsnInfo;

    public bool $needJsonSchema;

    public bool $needSymfonyAsserts;

    public function __construct(array $rpcConfigs, public RpcMainConfig $parent)
    {
        $this->keyForMethods = $rpcConfigs[self::RESPONSE][self::KEY_FOR_METHODS];
        $this->asyncDsnInfo = $rpcConfigs[self::RESPONSE][self::ASYNC_DSN_INFO];
        $this->needJsonSchema = true;
        $this->needSymfonyAsserts = $rpcConfigs[self::RESPONSE][self::VALIDATIONS][self::SYMFONY_ASSERTS];
    }

}