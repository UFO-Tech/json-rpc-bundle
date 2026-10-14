<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\ConfigService;

use InvalidArgumentException;

final readonly class RpcSecurityProviderConfig
{
    public const string NAME = 'name';
    public const string SERVICE = 'service';
    public const string API_GROUPS = 'api_groups';
    public const string TOKENS = RpcSecurityConfig::TOKENS;
    public const string PARAMETER = RpcMainConfig::NAME.'.'.RpcSecurityConfig::NAME.'.'.RpcSecurityConfig::PROVIDERS;

    public string $name;
    public ?string $service;
    public ?array $tokens;

    /** @var list<string> */
    public array $apiGroups;

    public function __construct(string $name, array $config)
    {
        $this->name = $name;
        if (isset($config[self::SERVICE]) && isset($config[self::TOKENS])) {
            throw new InvalidArgumentException('RPC security provider "'.$name.'" must configure exactly one of "service" or "clients_tokens".');
        }
        $this->service = $config[self::SERVICE] ?? null;
        $this->tokens = $config[self::TOKENS] ?? null;
        $this->apiGroups = $config[self::API_GROUPS] ?? [];
    }
}
