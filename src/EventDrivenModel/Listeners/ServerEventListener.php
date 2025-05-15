<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Throwable;
use TypeError;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Locker\LockerService;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceLocator;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\RpcObject\Events\BaseRpcEvent;
use Ufo\RpcObject\Events\RpcErrorEvent;
use Ufo\RpcObject\Events\RpcEvent;
use Ufo\RpcObject\Events\RpcPreExecuteEvent;
use Ufo\RpcObject\Events\RpcPreResponseEvent;
use Ufo\RpcObject\RPC\Cache;
use Ufo\RpcObject\RpcError;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Transformer\RpcResponseContextBuilder;

use function call_user_func_array;
use function preg_replace;

#[AsEventListener(RpcEvent::PRE_EXECUTE, 'process', priority: -1000)]
#[AsEventListener(RpcEvent::ERROR, 'onRpcError', priority: -1000)]
#[AsEventListener('kernel.terminate', 'terminate', priority: -1000)]
class ServerEventListener
{

    /**
     * @var BaseRpcEvent[]
     */
    protected array $events = [];

    public function __construct(
        protected LockerService $lockerService,
        #[Autowire('kernel.environment')]
        protected string $environment,
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

        $response = $this->createResponse($rpcRequest, $event->service, error: $event->rpcError);;
        $event->stopPropagation();
        return $response;
    }

    protected function createResponse(
        RpcRequest $request,
        Service $service,
        mixed $result = [],
        ?RpcError $error = null
    ): RpcResponse
    {
        $response = new RpcResponse(
            id: $request->getId() ?? 'not_processed',
            result: $result,
            error: $error,
            version: $request->getVersion(),
            requestObject: $request,
            cache: $service->getAttrCollection()->getAttribute(Cache::class),
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

    public function terminate(TerminateEvent $event): void
    {
        $preResponse = $this->events[RpcEvent::PRE_RESPONSE] ?? null;
        if (!$preResponse) return;
        /**
         * @var RpcPreResponseEvent $preResponse
         */
        try {
            $this->eventFactory->fire(
                RpcEvent::POST_RESPONSE,
                $preResponse->response,
                $preResponse->rpcRequest,
                $preResponse->service,
            );
        } catch (\Throwable $e) {
            $a=1;
        }
    }
}
