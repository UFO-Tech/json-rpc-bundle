<?php

namespace Ufo\JsonRpcBundle\Security\TokenHolders;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcTokenHolder;
use Ufo\RpcError\RpcTokenNotSentException;

use function strtolower;

use const PHP_EOL;

class CliTokenHolder implements IRpcTokenHolder
{
    protected ?Request $request = null;

    public function __construct(
        protected RpcMainConfig $rpcConfig,
        protected InputInterface $input,
    ) {}

    public function getTokenKey(): string
    {
       return strtolower($this->rpcConfig->securityConfig->tokenName);
    }

    public function getToken(): string
    {
        if (!($token = $this->input->getOption($this->getTokenKey()))) {
            throw new RpcTokenNotSentException("Token not set!" . PHP_EOL . "This protected command, use option --{$this->getTokenKey()}");
        }
        return $token;
    }

}