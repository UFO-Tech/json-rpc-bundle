<?php

namespace Ufo\JsonRpcBundle\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\DataCollector\RouterDataCollector;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Ufo\JsonRpcBundle\CliCommand\UfoRpcProcessCommand;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\Exceptions\AbstractJsonRpcBundleException;
use Ufo\JsonRpcBundle\Exceptions\ExceptionToArrayTransformer;
use Ufo\JsonRpcBundle\Exceptions\RpcTokenNotFoundInHeaderException;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Server\RpcServer;

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
            } catch (AbstractJsonRpcBundleException $e) {
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
            } catch (AbstractJsonRpcBundleException $e) {
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
