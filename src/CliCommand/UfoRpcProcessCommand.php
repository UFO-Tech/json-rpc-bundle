<?php

namespace Ufo\JsonRpcBundle\CliCommand;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;

use function strtolower;

#[AsCommand(
    name: UfoRpcProcessCommand::COMMAND_NAME,
    description: 'Handle async rpc request',
)]
class UfoRpcProcessCommand extends Command
{
    const string COMMAND_NAME = 'ufo:rpc:process';

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
                description: 'Json request object for provide',
                default: '[]'
            );
        $this
            ->addOption(
                strtolower($this->rpcConfig->securityConfig->tokenName),
                mode: $this->rpcConfig->securityConfig->protectedApi ? InputOption::VALUE_REQUIRED : InputOption::VALUE_OPTIONAL,
                description: 'Security token',
                default: $this->rpcConfig->securityConfig->protectedApi ? '' : null,
            );
        $this
            ->addOption(
                'async',
                mode: InputOption::VALUE_OPTIONAL,
                description: 'As async command',
            )
        ;
    }

}
