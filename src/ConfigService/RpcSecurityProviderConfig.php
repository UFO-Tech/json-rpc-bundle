<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\ConfigService;

use InvalidArgumentException;

final readonly class RpcSecurityProviderConfig
{
    public const string NAME = 'name';
    public const string SERVICE = 'service';
    public const string API_GROUPS = 'api_groups';
    public const string PARAMETER = RpcMainConfig::NAME.'.'.RpcSecurityConfig::NAME.'.'.RpcSecurityConfig::PROVIDERS;

    public string $name;
    public string $service;

    /** @var list<string> */
    public array $apiGroups;

    public function __construct(string $name, array $config)
    {
        $this->name = $name;
        $this->service = $config[self::SERVICE]
                         ?? throw new InvalidArgumentException('RPC security provider "' .self::SERVICE.'" must be a non-empty string.');
        $this->apiGroups = $config[self::API_GROUPS] ?? [];
    }
}
