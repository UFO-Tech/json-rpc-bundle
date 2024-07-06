<?php

namespace Ufo\JsonRpcBundle\ConfigService;

final readonly class RpcSecurityConfig
{
    const string NAME = 'security';
    const string PROTECTED_METHODS = 'protected_methods';
    const string TOKEN_KEY = 'token_key_in_header';
    const string TOKENS = 'clients_tokens';
    const string DEFAULT_TOKEN_KEY = 'Ufo-RPC-Token';

    public array $protectedMethods;

    public string $tokenKeyInHeader;

    public array $tokens;

    public function __construct(array $rpcConfigs, public RpcMainConfig $parent)
    {
        $this->protectedMethods = $rpcConfigs[self::PROTECTED_METHODS];
        $this->tokenKeyInHeader = $rpcConfigs[self::TOKEN_KEY];
        $this->tokens = $rpcConfigs[self::TOKENS];
    }

}