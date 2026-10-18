<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\JsonRpcBundle\Server\Async\RpcDetachedSlots;
use Ufo\RpcObject\RPC\APIGroups;
use Throwable;
use Ufo\JsonRpcBundle\CliCommand\UfoRpcProcessCommand;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\EventDrivenModel\Listeners\Traits\RpcRouteMatcherTrait;
use Ufo\JsonRpcBundle\EventDrivenModel\PostResponseQueue;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcTokenHolder;
use Ufo\JsonRpcBundle\Security\TokenHolders\CliTokenHolder;
use Ufo\JsonRpcBundle\Security\TokenHolders\HttpTokenHolder;
use Ufo\JsonRpcBundle\Server\RequestPrepare\IRpcHttpCarrier;
use Ufo\JsonRpcBundle\Server\RequestPrepare\IRpcRequestCarrier;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\JsonRpcBundle\Server\RequestPrepare\Holders\RpcFromCli;
use Ufo\JsonRpcBundle\Server\RequestPrepare\Holders\RpcFromHttp;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcError\RpcBadRequestException;
use Ufo\RpcError\RpcJsonParseException;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\RpcError\RpcTokenNotSentException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\Info;

use function fastcgi_finish_request;
use function getenv;
use function ignore_user_abort;
use function json_encode;
use function stream_get_contents;
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
    use RpcRouteMatcherTrait;

    public function __construct(
        protected RequestCarrier $requestCarrier,
        protected RpcEventFactory $eventFactory,
        protected RpcRequestHandler $requestHandler,
        protected RouterInterface $router,
        protected IRpcSecurity $rpcSecurity,
        protected RpcMainConfig $rpcConfig,
        protected ?ApiGroupContext $apiGroupContext = null,
        protected ?RpcDetachedSlots $detachedSlots = null,
        protected ?PostResponseQueue $postResponseQueue = null,
    ) {
        $this->postResponseQueue ??= new PostResponseQueue($this->eventFactory);
    }

    public function initHttpRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = $this->matchRoute($request);
        $this->apiGroupContext?->reset();
        if (!ApiController::isDocsRoute($route)) return;

        $this->apiGroupContext?->setGroup(
            $route[ApiGroupContext::ROUTE_GROUP] ?? APIGroups::DEFAULT_GROUP,
            $route[ApiGroupContext::ROUTE_NAME_PREFIX] ?? null
        );

        if (!ApiController::isApiRoute($route)) return;
        // CORS preflight only: an XHR request (X-Requested-With: XMLHttpRequest) is a regular RPC call.
        if ($request->isMethod(Request::METHOD_OPTIONS)) {
            $event->stopPropagation();
            $event->setResponse(new Response());
            return;
        }

        if ($request->isMethod(Request::METHOD_POST)) {
            $this->initRequest(
                new HttpTokenHolder($this->rpcConfig, $request),
                new RpcFromHttp($request, $route['ver'] ?? Info::DEFAULT_VERSION)
            );
        }
    }

    protected function checkRpcRoute(Request $request, ?string $method = Request::METHOD_POST): bool
    {
        return ApiController::isApiRoute($this->matchRoute($request))
            && (!$method || $request->isMethod($method));
    }

    /**
     * @param RequestEvent $event
     * @return void
     * @throws RpcAsyncRequestException
     * @throws RpcMethodNotFoundExceptionRpc
     * @throws RpcRuntimeException
     * @throws RpcTokenNotSentException
     * @throws WrongWayException
     * @throws ServiceNotFoundException
     * @throws RpcBadRequestException
     */
    public function magicPostController(RequestEvent $event): void
    {
        if ($this->checkRpcRoute($event->getRequest())) {
            $result = $this->requestHandler->handle();
            $response = match (true) {
                empty($result) => new Response('', Response::HTTP_NO_CONTENT),
                default => new JsonResponse($result)
            };
            $event->setResponse($response);
        }
    }

    /**
     * @param ConsoleCommandEvent $event
     * @throws RpcAsyncRequestException
     * @throws RpcBadRequestException
     * @throws RpcJsonParseException
     * @throws RpcMethodNotFoundExceptionRpc
     * @throws RpcRuntimeException
     * @throws RpcTokenNotSentException
     * @throws ServiceNotFoundException
     * @throws WrongWayException
     */
    public function parseCliRequestInArgs(ConsoleCommandEvent $event): void
    {
        if ($event->getCommand()?->getName() === UfoRpcProcessCommand::COMMAND_NAME) {
            $input = $event->getInput();
            $json = match ($input->getArgument('json')) {
                UfoRpcProcessCommand::JSON_FROM_STDIN => stream_get_contents(($input instanceof StreamableInputInterface ? $input->getStream() : null) ?? STDIN),
                UfoRpcProcessCommand::JSON_FROM_ENV => (string)getenv(UfoRpcProcessCommand::REQUEST_ENV),
                default => $input->getArgument('json'),
            };
            if (null !== $slot = $input->getOption(UfoRpcProcessCommand::DETACHED_SLOT)) {
                $this->detachedSlots?->hold((int)$slot);
            }
            $json = trim($json, '"');
            $this->apiGroupContext?->setGroup($input->getOption('api-group') ?? APIGroups::DEFAULT_GROUP);

            $this->initRequest(
                new CliTokenHolder($this->rpcConfig, $input),
                new RpcFromCli($json, $input->getOption('ver'))
            );
            // A detached process executes the request only if it takes it over from the worker first;
            // otherwise the worker has already executed it itself (hand-over timeout).
            $ticket = $input->getOption(UfoRpcProcessCommand::DETACHED_TICKET);
            if ($ticket !== null && !$this->detachedSlots?->claim($ticket)) {
                $event->getCommand()?->setCode(fn() => Command::SUCCESS);
                $event->stopPropagation();
                return;
            }
            $result = $this->requestHandler->handle();

            $event->getOutput()->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $event->getCommand()?->setCode(fn() => Command::SUCCESS);
            $event->stopPropagation();
        }
    }

    protected function initRequest(IRpcTokenHolder $holder, IRpcRequestCarrier $carrier): void
    {
        $this->eventFactory->reset();
        $this->rpcSecurity->setTokenHolder($holder);
        $this->requestCarrier->setCarrier($carrier);
        $this->rpcSecurity->isValidApiRequest();
    }

    /**
     * POST_RESPONSE of the requests executed for this HTTP request / command: the ones of the core flow
     * (taken from the event pool here) and the ones deferred by RpcExecutor (adapters).
     */
    public function firePostResponseEvent(TerminateEvent|ConsoleTerminateEvent $event): void
    {
        $request = $event instanceof TerminateEvent ? $event->getRequest() : null;
        $carrier = $this->requestCarrier->getCarrier();
        $ownPool = $request === null
            || ($carrier instanceof IRpcHttpCarrier && $carrier->getHttpRequest() === $request);
        if (!$ownPool && !$this->postResponseQueue->hasPending($request)) {
            return;
        }
        try {
            if ($ownPool) {
                $this->postResponseQueue->defer($this->postResponseQueue->takeFromPool(), $request);
            }
            if (!$this->postResponseQueue->hasPending($request)) {
                return;
            }
            if ($request !== null) {
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                // The client may already be gone: the work after the response must finish anyway
                // (without FPM PHP aborts the script on the first output to a closed connection).
                ignore_user_abort(true);
            }
            $this->postResponseQueue->flush($request);
        } catch (Throwable) {} finally {
            $this->apiGroupContext?->reset();
        }
    }

}
