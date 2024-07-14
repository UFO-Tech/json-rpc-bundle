<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\Exceptions\StopHandlerException;
use Ufo\JsonRpcBundle\Server\RpcRequestHelper;
use Ufo\RpcError\ExceptionToArrayTransformer;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcJsonParseException;
use Ufo\RpcError\RpcTokenNotFoundInHeaderException;
use Ufo\RpcObject\RpcError;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Transformer\RpcResponseContextBuilder;

use function in_array;

#[AsEventListener(KernelEvents::EXCEPTION, 'onKernelException', 1000)]
class RpcErrorListener implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire('kernel.environment')]
        protected string $environment,
        protected RouterInterface $router,
        protected SerializerInterface $serializer,
        protected RpcResponseContextBuilder $contextBuilder
    ) {}

    /**
     * @throws RpcJsonParseException
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $apiPaths = [
            $this->router->getRouteCollection()->get(ApiController::API_ROUTE)->getPath(),
        ];
        $exception = $event->getThrowable();
        if ($exception instanceof StopHandlerException) return;
        if (!in_array($request->getPathInfo(), $apiPaths)) return;

        $exceptionToArray = new ExceptionToArrayTransformer($exception, $this->environment);
        $errorObject = $this->createError($exception, $exceptionToArray->infoByEnvironment());

        try {
            $this->httpGetErrorHandle($exception, $errorObject, $event);
            $this->invalidParseJsonHandle($exception, $errorObject, $event);
        } catch (StopHandlerException) {
            return;
        }

        $rpcRequestHelper = new RpcRequestHelper($request);
        if (!$rpcRequestHelper->isBatchRequest()) {
            $result = $this->createResponse($rpcRequestHelper->getRequestObject()->getId(), $errorObject);
        } else {
            $result = [];
            foreach ($rpcRequestHelper->getRequestObject()->getCollection() as $rpcObject) {
                $result[] = $this->createResponse($rpcObject->getId(), $errorObject);
            }
        }
        $this->sendResponse($event, $result);
    }

    protected function invalidParseJsonHandle(\Throwable $exception, RpcError $error, ExceptionEvent $event): void
    {
        if (!$exception instanceof RpcJsonParseException) {
            return;
        }
        $result = $this->createResponse('invalid_json_format', $error);
        $this->sendResponse($event, $result);
        throw new StopHandlerException();
    }

    protected function httpGetErrorHandle(\Throwable $exception, RpcError $error, ExceptionEvent $event): void
    {
        if ($event->getRequest()->getMethod() !== Request::METHOD_GET) {
            return;
        }
        $result = $this->createResponse('api_doc_error', $error);
        $this->sendResponse($event, $result);
        throw new StopHandlerException();
    }

    protected function sendResponse(ExceptionEvent $event, array $result): void
    {
        $event->setResponse(new JsonResponse($result, $this->getHttpCodeFromException($event->getThrowable())));
        $event->stopPropagation();
        $event->allowCustomResponseCode();
    }

    protected function createResponse(string|int $id, RpcError $error): array
    {
        $response = new RpcResponse(
            $id,
            error: $error
        );
        return $this->serializer->normalize(
            $response,
            context:$this->contextBuilder->withGroup(RpcResponse::IS_ERROR)->toArray()
        );
    }

    protected function createError(\Throwable $e, array $data = []): RpcError
    {
        return new RpcError(
            $e->getCode(),
            $e->getMessage(),
            $data,
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [];
    }

    protected function getHttpCodeFromException(\Throwable $e): int
    {
        return match ($e::class) {
            RpcJsonParseException::class => Response::HTTP_INTERNAL_SERVER_ERROR,
            RpcTokenNotFoundInHeaderException::class => Response::HTTP_UNAUTHORIZED,
            RpcInvalidTokenException::class => Response::HTTP_FORBIDDEN,
            default => Response::HTTP_OK
        };
    }
}
