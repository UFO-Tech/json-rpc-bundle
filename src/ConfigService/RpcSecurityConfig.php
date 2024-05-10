<?php

namespace Ufo\JsonRpcBundle\ConfigService;

final readonly class RpcSecurityConfig
{
    const NAME = 'security';
    const PROTECTED_METHODS = 'protected_methods';
    const TOKEN_KEY = 'token_key_in_header';
    const TOKENS = 'clients_tokens';
    const DEFAULT_TOKEN_KEY = 'Ufo-RPC-Token';
    public array $protectedMethods;
    public string $tokenKeyInHeader;
    public array $tokens;

    public function __construct(array $rpcConfigs)
    {
        $this->protectedMethods = $rpcConfigs[self::PROTECTED_METHODS];
        $this->tokenKeyInHeader = $rpcConfigs[self::TOKEN_KEY];
        $this->tokens = $rpcConfigs[self::TOKENS];
    }
}