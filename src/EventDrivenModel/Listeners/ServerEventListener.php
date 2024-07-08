<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Throwable;
use TypeError;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Serializer\RpcResponseContextBuilder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\RpcObject\Events\RpcErrorEvent;
use Ufo\RpcObject\Events\RpcEvent;
use Ufo\RpcObject\Events\RpcPreExecuteEvent;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

use function call_user_func_array;
use function preg_replace;

#[AsEventListener(RpcEvent::PRE_EXECUTE, 'process', priority: -1000)]
#[AsEventListener(RpcEvent::ERROR, 'onRpcError', priority: -1000)]
class ServerEventListener
{
    public function __construct(
        #[Autowire('kernel.environment')]
        protected string $environment,
        protected RpcEventFactory $eventFactory,
        protected RpcResponseContextBuilder $contextBuilder,
    ) {}

    public function process(RpcPreExecuteEvent $event): void
    {
        if ($event->rpcRequest->hasError()) {
            $this->eventFactory->fireError($event->rpcRequest, $event->rpcRequest->getError());
            $event->stopPropagation();
            return;
        }
        try {
            $result = call_user_func_array([
                $event->service->getProcedure(),
                $event->service->getMethodName(),
            ], $event->params);
        } catch (TypeError $e) {
            $message = preg_replace('/.*\\\\/', '', $e->getMessage());
            $message = preg_replace('/Argument #\d+ \(\$([a-zA-Z0-9_]+)\)/', 'Parameter "$1"', $message);
            $this->eventFactory->fireError($event->rpcRequest, new RpcBadParamException($message));
            return;
        } catch (Throwable $e) {
            $this->eventFactory->fireError($event->rpcRequest, RpcRuntimeException::fromThrowable($e));
            return;
        }
        $this->eventFactory->fire(RpcEvent::POST_EXECUTE, $result);

        $this->createResponse($event->rpcRequest, $result, $event->service);
        $event->stopPropagation();
    }

    public function onRpcError(RpcErrorEvent $event): RpcResponse
    {
        $rpcRequest = $event->rpcRequest;
        if (!$rpcRequest->hasError()) {
            $rpcRequest->setError($event->exception);
        }

        $response = new RpcResponse(
            id: $rpcRequest->getId() ?? 'not_processed',
            error: $event->rpcError,
            version: $rpcRequest->getVersion(),
            requestObject: $rpcRequest,
            contextBuilder: $this->contextBuilder
        );
        $this->eventFactory->fire(RpcEvent::RESPONSE, $response);
        $event->stopPropagation();
        return $response;
    }

    protected function createResponse(RpcRequest $request, mixed $result, Service $service): RpcResponse
    {
        $response = new RpcResponse(
            id: $request->getId(),
            result: $result,
            version: $request->getVersion(),
            requestObject: $request,
            cache: $service->getCacheInfo(),
            contextBuilder: $this->contextBuilder
        );
        $this->eventFactory->fire(RpcEvent::RESPONSE, $response);
        return $response;
    }

}
