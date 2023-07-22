<?php

namespace Ufo\JsonRpcBundle\CliCommand;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Serializer\RpcErrorNormalizer;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\RpcObject\RpcRequest;

#[AsCommand(
    name: UfoRpcProcessCommand::COMMAND_NAME,
    description: 'Handle async rpc request',
)]
class UfoRpcProcessCommand extends Command
{
    const COMMAND_NAME = 'ufo:rpc:process';

    public function __construct(
        protected IRpcSecurity $rpcSecurity,
        protected RpcRequestHandler $requestHandler,
        protected SerializerInterface $serializer,
    )
    {
        parent::__construct();
    }


    protected function configure(): void
    {
        $this
            ->addArgument('json', InputArgument::REQUIRED, 'Json request object for provide')
            ->addOption('token', 't', InputOption::VALUE_OPTIONAL, 'Security token')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {

            $json = trim($input->getArgument('json'), '"');
            $request = RpcRequest::fromJson($json);
            $result = $this->requestHandler->provideSingleRequestToResponse($request);
            $context = [
                AbstractNormalizer::GROUPS => [$result->getResponseSignature()],
                RpcErrorNormalizer::RPC_CONTEXT => true,
            ];
            $result = $this->serializer->serialize($result, 'json', $context);
            $io->writeln($result);
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->getErrorStyle()->error([
                $e->getMessage(),
                $e->getFile() . ': ' . $e->getLine()
            ]);
            return Command::FAILURE;
        }
    }
}
