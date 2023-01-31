<?php

namespace Ufo\JsonRpcBundle\EventListener;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Ufo\JsonRpcBundle\CliCommand\UfoRpcProcessCommand;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\RpcTokenNotFoundInHeaderException;

class HandleCliListener implements EventSubscriberInterface
{

    public function __construct(
        protected IRpcSecurity $rpcSecurity,
        protected array $protectedMethods
    )
    {
    }

    public function onConsoleCommand(ConsoleCommandEvent $event)
    {
        $io = new SymfonyStyle($event->getInput(), $event->getOutput());

        if ($event->getCommand()->getName() === 'ufo:rpc:process') {
            try {
                if (in_array('POST', $this->protectedMethods)) {
                    if (!($token = $event->getInput()->getOption('token'))) {

                        throw new RpcTokenNotFoundInHeaderException(
                            'Token not set!' . PHP_EOL . 'This protected command, use option -t (--token)'
                        );
                    }
                    $this->rpcSecurity->isValidToken($token);
                }
            } catch (AbstractRpcErrorException $e) {
                $io->error([
                    $e->getMessage()
                ]);
                $event->getCommand()->setCode(
                    function () {
                        return Command::FAILURE;
                    }
                );
                die;
            }
        }
    }

    public function onConsoleError(ConsoleErrorEvent $event)
    {
        $io = new SymfonyStyle($event->getInput(), $event->getOutput());

        if ($event->getCommand()->getName() === UfoRpcProcessCommand::COMMAND_NAME) {
            try {
                if (in_array('POST', $this->protectedMethods)) {
                    if (!($token = $event->getInput()->getOption('token'))) {

                        throw new RpcTokenNotFoundInHeaderException(
                            'Token not set!' . PHP_EOL . 'This protected command, use option -t (--token)'
                        );
                    }
                    $this->rpcSecurity->isValidToken($token);
                }
            } catch (AbstractRpcErrorException $e) {
                $io->error([
                    $e->getMessage()
                ]);
                die;
            }
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            ConsoleEvents::COMMAND => 'onConsoleCommand',
            ConsoleEvents::ERROR  => 'onConsoleError',
        ];
    }
}
