<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Throwable;
use Ufo\JsonRpcBundle\CliCommand\UfoRpcProcessCommand;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcTokenHolder;
use Ufo\JsonRpcBundle\Security\TokenHolders\CliTokenHolder;
use Ufo\JsonRpcBundle\Security\TokenHolders\HttpTokenHolder;
use Ufo\JsonRpcBundle\Server\RequestPrepare\IRpcRequestCarrier;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\JsonRpcBundle\Server\RequestPrepare\Holders\RpcFromCli;
use Ufo\JsonRpcBundle\Server\RequestPrepare\Holders\RpcFromHttp;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcJsonParseException;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreResponseEvent;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\RpcError\RpcTokenNotSentException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\Info;

use function fastcgi_finish_request;
use function json_encode;
use function trim;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_UNICODE;

#[AsEventListener(KernelEvents::REQUEST, method: 'initHttpRequest', priority: 240)]
#[AsEventListener(KernelEvents::REQUEST, method: 'magicPostController', priority: 230)]
#[AsEventListener(KernelEvents::TERMINATE, method: 'firePostResponseEvent', priority: 1000000)]

#[AsEventListener(ConsoleEvents::COMMAND, method: 'parseCliRequestInArgs', priority: 999999)]
#[AsEventListener(ConsoleEvents::TERMINATE, method: 'firePostResponseEvent', priority: 1000000)]
class SymfonyFlowListener
{
    public function __construct(
        protected RequestCarrier $requestCarrier,
        protected RpcEventFactory $eventFactory,
        protected RpcRequestHandler $requestHandler,
        protected RouterInterface $router,
        protected IRpcSecurity $rpcSecurity,
        protected RpcMainConfig $rpcConfig,
    ) {}

    public function initHttpRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($this->checkRpcRoute($request, null) && ($request->isMethod(Request::METHOD_OPTIONS) || $request->isXmlHttpRequest())) {
            $event->stopPropagation();
            $event->setResponse(new Response());
            return;
        }

        if ($this->checkRpcRoute($request)) {
            $route = $this->router->match($request->getPathInfo());

            $this->initRequest(
                new HttpTokenHolder($this->rpcConfig, $request),
                new RpcFromHttp($request, $route['ver'] ?? Info::DEFAULT_VERSION)
            );
        }
    }

    protected function checkRpcRoute(Request $request, ?string $method = Request::METHOD_POST): bool
    {
        $route = $this->router->match($request->getPathInfo());
        $routeName = $route['_route'] ?? '';
        return ($routeName === ApiController::API_ROUTE_VER || $routeName === ApiController::API_ROUTE)
            && (!$method || $request->isMethod($method))
        ;
    }

    /**
     * @param RequestEvent $event
     * @return void
     * @throws RpcAsyncRequestException
     * @throws RpcInvalidTokenException
     * @throws RpcJsonParseException
     * @throws RpcMethodNotFoundExceptionRpc
     * @throws RpcRuntimeException
     * @throws RpcTokenNotSentException
     * @throws WrongWayException
     */
    public function magicPostController(RequestEvent $event): void
    {
        if ($this->checkRpcRoute($event->getRequest())) {
            $result = $this->requestHandler->handle();
            $event->setResponse(new JsonResponse($result));
        }
    }

    /**
     * @param ConsoleCommandEvent $event
     * @throws RpcAsyncRequestException
     * @throws RpcInvalidTokenException
     * @throws RpcJsonParseException
     * @throws RpcMethodNotFoundExceptionRpc
     * @throws RpcRuntimeException
     * @throws RpcTokenNotSentException
     * @throws WrongWayException
     */
    public function parseCliRequestInArgs(ConsoleCommandEvent $event): void
    {
        if ($event->getCommand()?->getName() === UfoRpcProcessCommand::COMMAND_NAME) {
            $input = $event->getInput();
            $json = trim($input->getArgument('json'), '"');

            $this->initRequest(
                new CliTokenHolder($this->rpcConfig, $input),
                new RpcFromCli($json, $input->getOption('ver'))
            );
            $result = $this->requestHandler->handle();

            $event->getOutput()->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $event->getCommand()?->setCode(fn() => Command::SUCCESS);
            $event->stopPropagation();
        }
    }

    protected function initRequest(IRpcTokenHolder $holder, IRpcRequestCarrier $carrier): void
    {
        $this->rpcSecurity->setTokenHolder($holder);
        $this->requestCarrier->setCarrier($carrier);
        $this->rpcSecurity->isValidApiRequest();
    }

    public function firePostResponseEvent(TerminateEvent|ConsoleTerminateEvent $event): void
    {
        try {
            if ($this->requestCarrier->getCarrier() instanceof RpcFromHttp) fastcgi_finish_request();

            $preResponse = $this->eventFactory->getEvent(RpcEvent::getEventFQSN(RpcEvent::PRE_RESPONSE));
            $event->stopPropagation();
            /**
             * @var RpcPreResponseEvent $preResponse
             */
            $this->eventFactory->fire(
                RpcEvent::POST_RESPONSE,
                $preResponse->response,
                $preResponse->rpcRequest,
                $preResponse->service,
            );
            $event->stopPropagation();

        } catch (Throwable) {}
    }

}
