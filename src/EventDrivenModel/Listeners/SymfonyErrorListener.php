<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Throwable;
use Ufo\JsonRpcBundle\CliCommand\UfoRpcProcessCommand;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Exceptions\StopHandler;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\RpcError\ExceptionToArrayTransformer;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcJsonParseException;
use Ufo\RpcError\RpcTokenNotSentException;
use Ufo\RpcObject\RpcError;
use Ufo\RpcObject\RpcResponse;

use function in_array;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_UNICODE;


#[AsEventListener(KernelEvents::EXCEPTION, method: 'requestJsonParseError', priority: 999)]
#[AsEventListener(KernelEvents::EXCEPTION, method: 'documentationError', priority: 998)]
#[AsEventListener(KernelEvents::EXCEPTION, method: 'otherErrors', priority: 997)]

#[AsEventListener(ConsoleEvents::ERROR, method: 'cliJsonParseError', priority: 999999)]
#[AsEventListener(ConsoleEvents::ERROR, method: 'cliRpcError', priority: 99999)]
class SymfonyErrorListener
{
    const string UNDEFINED = 'undefined';
    const string API_DOC_ERROR = 'api_doc_error';
    const string INVALID_JSON_FORMAT = 'invalid_json_format';

    public function __construct(
        protected RpcEventFactory $eventFactory,
        protected RequestCarrier $requestCarrier,
        protected RpcRequestHandler $requestHandler,

        #[Autowire('%kernel.environment%')]
        protected string $environment,
        protected RouterInterface $router,
    ) {}

    public function requestJsonParseError(ExceptionEvent $event): void
    {
        if (($this->router->match($event->getRequest()->getPathInfo())['_route'] ?? '')  !== ApiController::API_ROUTE) return;

        $exception = $event->getThrowable();
        if (!$exception instanceof RpcJsonParseException) return;

        $result = $this->createResponse(static::INVALID_JSON_FORMAT, $exception);

        $this->sendResponse($event, $this->requestHandler->responseToArray($result));
    }

    public function cliJsonParseError(ConsoleErrorEvent $event): void
    {
        if ($event->getCommand()?->getName() !== UfoRpcProcessCommand::COMMAND_NAME) return;

        $exception = $event->getError();
        if (!$exception instanceof RpcJsonParseException) return;

        $result = $this->createResponse(static::INVALID_JSON_FORMAT, $exception);

        $this->writeResponse($event, $this->requestHandler->responseToArray($result));

    }

    public function documentationError(ExceptionEvent $event): void
    {
        $route = $this->router->match($event->getRequest()->getPathInfo())['_route'] ?? '';
        if (!in_array($route, ApiController::API_DOC_ROUTES)) return;
        if ($event->getRequest()->getMethod() !== Request::METHOD_GET) return;

        $exception = $event->getThrowable();
        if ($exception instanceof StopHandler) return;

        $result = $this->createResponse(static::API_DOC_ERROR, $exception);

        $this->sendResponse($event, $this->requestHandler->responseToArray($result));
    }


    public function otherErrors(ExceptionEvent $event): void
    {
        if (($this->router->match($event->getRequest()->getPathInfo())['_route'] ?? '')  !== ApiController::API_ROUTE) return;

        $exception = $event->getThrowable();
        if ($exception instanceof StopHandler) return;

        try {
            $result = [];
            foreach ($this->requestCarrier->getBatchRequestObject()->getCollection() as $rpcObject) {
                $resp = $rpcObject->getResponseObject() ?? $this->createResponse($rpcObject->getId(), $exception);
                $result[] = $this->requestHandler->responseToArray($resp);
            }
        } catch (Throwable $e) {
            $rpcObject = $this->requestCarrier->getRequestObject();
            $resp = $rpcObject->getResponseObject() ?? $this->createResponse($rpcObject->getId(), $exception);
            $result = $this->requestHandler->responseToArray($resp);
        }

        $this->sendResponse($event, $result);
    }

    public function cliRpcError(ConsoleErrorEvent $event): void
    {
        if ($event->getCommand()?->getName() !== UfoRpcProcessCommand::COMMAND_NAME) return;

        $this->eventFactory->fireError(
            $this->requestCarrier->getRequestObject(),
            $event->getError()
        );

        try {
            $result = [];
            foreach ($this->requestCarrier->getBatchRequestObject()->getCollection() as $rpcObject) {
                $result[] = $this->requestHandler->responseToArray($rpcObject->getResponseObject());
            }
        } catch (Throwable $e) {
            $result = $this->requestHandler->responseToArray($this->requestCarrier->getRequestObject()->getResponseObject());
        }

        $this->writeResponse($event, $result);

    }


    protected function getHttpCodeFromException(Throwable $e): int
    {
        return match ($e::class) {
            RpcJsonParseException::class => Response::HTTP_INTERNAL_SERVER_ERROR,
            RpcTokenNotSentException::class => Response::HTTP_UNAUTHORIZED,
            RpcInvalidTokenException::class => Response::HTTP_FORBIDDEN,
            default => Response::HTTP_OK
        };
    }

    protected function sendResponse(ExceptionEvent $event, array $result): void
    {
        $event->setResponse(new JsonResponse($result, $this->getHttpCodeFromException($event->getThrowable())));
        $event->stopPropagation();
        $event->allowCustomResponseCode();
    }

    protected function writeResponse(ConsoleErrorEvent $event, array $result): void
    {
        $event->getOutput()->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $event->getCommand()?->setCode(fn() => Command::SUCCESS);
        $event->stopPropagation();
    }

    protected function createResponse(string|int $id, Throwable $e, array $data = []): RpcResponse
    {
        $exceptionToArray = new ExceptionToArrayTransformer($e, $this->environment);

        return new RpcResponse(
            $id,
            error: new RpcError(
                $e->getCode(),
                $e->getMessage(),
                [
                    ...$exceptionToArray->infoByEnvironment(),
                    ...$data,
                ],
            )
        );
    }
}
