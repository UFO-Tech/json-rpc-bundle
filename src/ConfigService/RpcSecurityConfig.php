<?php

namespace Ufo\JsonRpcBundle\ConfigService;

final readonly class RpcSecurityConfig
{
    const string NAME = 'security';
    const string PROTECTED_API = 'protected_api';
    const string PROTECTED_DOC = 'protected_doc';
    const string TOKEN_NAME = 'token_name';
    const string TOKENS = 'clients_tokens';
    const string PROVIDERS = 'providers';
    const string DEFAULT_TOKEN_KEY = 'Ufo-RPC-Token';

    public bool $protectedApi;
    public bool $protectedDoc;

    public string $tokenName;

    public array $tokens;

    /** @var array<string, RpcSecurityProviderConfig> */
    public array $providers;

    public function __construct(array $rpcConfigs, public RpcMainConfig $parent)
    {
        $this->protectedApi = $rpcConfigs[self::PROTECTED_API] ?? false;
        $this->protectedDoc = $rpcConfigs[self::PROTECTED_DOC] ?? false;

        $this->tokenName = $rpcConfigs[self::TOKEN_NAME] ?? self::DEFAULT_TOKEN_KEY;
        $this->tokens = $rpcConfigs[self::TOKENS] ?? [];
        $providers = [];
        foreach ($rpcConfigs[self::PROVIDERS] ?? [] as $name => $provider) {
            $providers[$name] = new RpcSecurityProviderConfig($name, $provider);
        }
        $this->providers = $providers;
    }
}