<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Throwable;
use TypeError;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Locker\LockerService;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceLocator;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\BaseRpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcErrorEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreExecuteEvent;
use Ufo\RpcObject\RPC\Cache;
use Ufo\RpcObject\RpcError;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Transformer\RpcResponseContextBuilder;

use function call_user_func_array;
use function preg_replace;

#[AsEventListener(RpcEvent::PRE_EXECUTE, method: 'process', priority: -1000)]
#[AsEventListener(RpcEvent::ERROR, method: 'onRpcError', priority: -1000)]
class RpcServerListener
{

    /**
     * @var BaseRpcEvent[]
     */
    protected array $events = [];

    public function __construct(
        protected LockerService $lockerService,
        protected RpcEventFactory $eventFactory,
        protected ServiceLocator $serviceLocator,
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
                $this->serviceLocator->get($event->service->getProcedureFQCN()),
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
        } finally {
            $this->lockerService->release();
        }

        $this->createResponse($event->rpcRequest, $event->service, result: $result);

        $this->events[RpcEvent::POST_EXECUTE] = $this->eventFactory->fire(
            RpcEvent::POST_EXECUTE,
            $result,
            $event->rpcRequest,
            $event->service,
            $event->params
        );

        $event->stopPropagation();
    }

    public function onRpcError(RpcErrorEvent $event): RpcResponse
    {
        $rpcRequest = $event->rpcRequest;
        if (!$rpcRequest->hasError()) {
            $rpcRequest->setError($event->exception);
        }

        $response = $this->createResponse($rpcRequest, error: $event->rpcError);
        $event->stopPropagation();
        return $response;
    }

    protected function createResponse(
        RpcRequest $request,
        ?Service $service = null,
        mixed $result = null,
        ?RpcError $error = null
    ): RpcResponse
    {
        $response = new RpcResponse(
            id: $request->getId() ?? 'not_processed',
            result: $result,
            error: $error,
            version: $request->getVersion(),
            requestObject: $request,
            cache: $service?->getAttrCollection()->getAttribute(Cache::class),
            contextBuilder: $this->contextBuilder
        );

        $request->setResponse($response);

        $this->events[RpcEvent::PRE_RESPONSE] = $this->eventFactory->fire(
            RpcEvent::PRE_RESPONSE,
            $response,
            $request,
            $service,
        );
        return $response;
    }
}
