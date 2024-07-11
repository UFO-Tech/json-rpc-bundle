<?php

namespace Ufo\JsonRpcBundle\ConfigService;

final readonly class RpcSecurityConfig
{
    const string NAME = 'security';
    const string PROTECTED_API = 'protected_api';
    const string PROTECTED_DOC = 'protected_doc';
    const string TOKEN_KEY = 'token_name';
    const string TOKENS = 'clients_tokens';
    const string DEFAULT_TOKEN_KEY = 'Ufo-RPC-Token';

    public array $protectedMethods;
    public bool $protectedApi;
    public bool $protectedDoc;

    public string $tokenKeyInHeader;

    public array $tokens;

    public function __construct(array $rpcConfigs, public RpcMainConfig $parent)
    {
        $this->protectedApi = $rpcConfigs[self::PROTECTED_API];
        $this->protectedDoc = $rpcConfigs[self::PROTECTED_DOC];

        $this->protectedMethods = [
            'GET' =>  $rpcConfigs[self::PROTECTED_DOC],
            'POST' =>  $rpcConfigs[self::PROTECTED_API],
        ];

        $this->tokenKeyInHeader = $rpcConfigs[self::TOKEN_KEY];
        $this->tokens = $rpcConfigs[self::TOKENS];
    }

}