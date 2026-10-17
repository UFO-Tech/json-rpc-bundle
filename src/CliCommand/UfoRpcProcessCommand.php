<?php

namespace Ufo\JsonRpcBundle\CliCommand;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RPC\APIGroups;

use function strtolower;

#[AsCommand(
    name: UfoRpcProcessCommand::COMMAND_NAME,
    description: 'Handle async rpc request',
)]
class UfoRpcProcessCommand extends Command
{
    const string COMMAND_NAME = 'ufo:rpc:process';
    /**
     * Passed instead of the json argument: the request is read from stdin (keeps it out of the process list).
     */
    const string JSON_FROM_STDIN = '-';
    /**
     * Passed instead of the json argument: the request is read from the REQUEST_ENV variable.
     * Used by a detached process, whose stdin is /dev/null.
     */
    const string JSON_FROM_ENV = '@env';
    const string REQUEST_ENV = 'UFO_RPC_REQUEST';
    /**
     * Slot of RpcDetachedSlots held by a detached process while it runs.
     */
    const string DETACHED_SLOT = 'detached-slot';
    /**
     * Hand-over ticket of RpcDetachedSlots: the request is executed only if this process claims it.
     */
    const string DETACHED_TICKET = 'detached-ticket';
    /**
     * Env variable with the security token, used when the token option is not given (keeps it out of the process list).
     */
    const string TOKEN_ENV = 'UFO_RPC_TOKEN';

    public function __construct(
        protected RpcMainConfig $rpcConfig,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'json',
                mode: InputArgument::OPTIONAL,
                description: 'Json request object for provide, or "'.self::JSON_FROM_STDIN.'" to read it from stdin',
                default: '[]'
            )
            ->addOption(
                strtolower($this->rpcConfig->securityConfig->tokenName),
                mode: $this->rpcConfig->securityConfig->protectedApi ? InputOption::VALUE_REQUIRED : InputOption::VALUE_OPTIONAL,
                description: 'Security token (or env '.self::TOKEN_ENV.')',
                default: $this->rpcConfig->securityConfig->protectedApi ? '' : null,
            )
            ->addOption(
                'async',
                mode: InputOption::VALUE_OPTIONAL,
                description: 'As async command',
            )
            ->addOption(
                'api-group',
                mode: InputOption::VALUE_REQUIRED,
                description: 'RPC API group',
                default: APIGroups::DEFAULT_GROUP
            )
            ->addOption(
                'ver',
                mode: InputOption::VALUE_OPTIONAL,
                description: 'API version',
                default: Info::DEFAULT_VERSION
            )
            ->addOption(
                self::DETACHED_SLOT,
                mode: InputOption::VALUE_REQUIRED,
                description: 'Detached process slot (internal)',
            )
            ->addOption(
                self::DETACHED_TICKET,
                mode: InputOption::VALUE_REQUIRED,
                description: 'Detached process hand-over ticket (internal)',
            )
        ;
    }

}
