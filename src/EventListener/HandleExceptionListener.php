<?php

namespace Ufo\JsonRpcBundle\EventListener;


use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\Serializer\RpcResponseContextBuilder;
use Ufo\JsonRpcBundle\Server\RpcRequestHelper;
use Ufo\JsonRpcBundle\Server\RpcServer;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\ExceptionToArrayTransformer;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcJsonParseException;
use Ufo\RpcError\RpcTokenNotFoundInHeaderException;
use Ufo\RpcObject\RpcError;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

use function in_array;

class HandleExceptionListener implements EventSubscriberInterface
{
    public function __construct(
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
            $this->router->getRouteCollection()->get(ApiController::COLLECTION_ROUTE)->getPath(),
            $this->router->getRouteCollection()->get(ApiController::OPEN_RPC_ROUTE)->getPath(),
        ];
        if (!in_array($request->getPathInfo(), $apiPaths)) {
            return;
        }
        $exception = $event->getThrowable();
        $exceptionToArray = new ExceptionToArrayTransformer($exception, $this->environment);

        $rpcRequestHelper = new RpcRequestHelper($request);
        if (!$rpcRequestHelper->isBatchRequest()) {
            $result = $this->createResponse($rpcRequestHelper->getRequestObject(), $exception,
                $exceptionToArray->infoByEnvironment());
        } else {
            $result = [];
            foreach ($rpcRequestHelper->getRequestObject()->getCollection() as $rpcObject) {
                $result[] = $this->createResponse($rpcObject, $exception, $exceptionToArray->infoByEnvironment());
            }
        }

        $event->setResponse(new JsonResponse($result, $this->getHttpCodeFromException($event->getThrowable())));
        $event->stopPropagation();
        $event->allowCustomResponseCode();
    }

    protected function createResponse(RpcRequest $rpcRequest, \Throwable $e, array $data = []): array
    {
        $response = new RpcResponse(
            $rpcRequest->getId(),
            error: new RpcError(
                $e->getCode(),
                $e->getMessage(),
                $data,
            )
        );
        return $this->serializer->normalize(
            $response,
            context:$this->contextBuilder->withGroup(RpcResponse::IS_ERROR)->toArray()
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    protected function getHttpCodeFromException(\Throwable $e): int
    {
        return match ($e::class) {
            RpcTokenNotFoundInHeaderException::class => Response::HTTP_UNAUTHORIZED,
            RpcInvalidTokenException::class => Response::HTTP_FORBIDDEN,
            default => Response::HTTP_OK
        };
    }
}
