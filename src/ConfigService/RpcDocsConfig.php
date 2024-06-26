<?php

namespace Ufo\JsonRpcBundle\ConfigService;

final readonly class RpcDocsConfig
{
    const NAME = 'docs';
    const RESPONSE = 'response';
    const KEY_FOR_METHODS = 'key_for_methods';
    const ASYNC_DSN_INFO = 'async_dsn_info';
    const VALIDATIONS = 'validations';
    const JSON_SCHEMA = 'json_schema';
    const SYMFONY_ASSERTS = 'symfony_asserts';
    const DEFAULT_KEY_FOR_METHODS = 'services';

    public string $keyForMethods;

    public bool $asyncDsnInfo;

    public bool $needJsonSchema;

    public bool $needSymfonyAsserts;

    public function __construct(array $rpcConfigs, public RpcMainConfig $parent)
    {
        $this->keyForMethods = $rpcConfigs[self::RESPONSE][self::KEY_FOR_METHODS];
        $this->asyncDsnInfo = $rpcConfigs[self::RESPONSE][self::ASYNC_DSN_INFO];
        $this->needJsonSchema = $rpcConfigs[self::RESPONSE][self::VALIDATIONS][self::JSON_SCHEMA];
        $this->needSymfonyAsserts = $rpcConfigs[self::RESPONSE][self::VALIDATIONS][self::SYMFONY_ASSERTS];
    }

}