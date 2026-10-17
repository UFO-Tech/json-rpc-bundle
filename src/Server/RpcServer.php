<?php

namespace Ufo\JsonRpcBundle\Server;

use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\JsonRpcBundle\Server\RpcCache\RpcCacheService;
use Ufo\JsonRpcBundle\Server\ServiceMap\IServiceHolder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreResponseEvent;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

class RpcServer
{
    protected ?RpcRequest $requestObject = null;
    protected RpcResponse $responseObject;
    protected bool $responseListenerAdded = false;

    public function __construct(
        readonly public IServiceHolder $serviceHolder,
        protected RpcEventFactory $eventFactory,
        protected RpcCacheService $cache,
    ) {}

    public function newRequest(RpcRequest $requestObject): void
    {
        $this->requestObject = $requestObject;
    }

    /**
     * @param RpcRequest $request
     * @return RpcResponse
     * @throws RpcMethodNotFoundExceptionRpc
     */
    public function handleRpcRequest(RpcRequest $request, ?Service $service = null): RpcResponse
    {
        $method = $request->getMethod();
        try {
            $service ??= $this->serviceHolder->getService($method, $request->apiVersion);
        } catch (ServiceNotFoundException $e) {
            throw new RpcMethodNotFoundExceptionRpc($e->getMessage());
        }

        // Once per instance: the listener only stores the response, and adding it on every call
        // makes the listener list grow without bound in long-running workers.
        if (!$this->responseListenerAdded) {
            $this->eventFactory->addListener(
                RpcEvent::PRE_RESPONSE,
                $this->onRpcResponseCallback(),
                -100000
            );
            $this->responseListenerAdded = true;
        }

        $this->eventFactory->fire(
            RpcEvent::PRE_EXECUTE,
            $this->requestObject,
            $service,
            $request->getParams()
        );

        return $this->responseObject;
    }

    public function onRpcResponseCallback(): callable
    {
        return function (RpcPreResponseEvent $event) {
            $this->responseObject = $event->rpcRequest->getResponseObject();
        };
    }

    /**
     * @param RpcRequest $singleRequest
     * @return RpcResponse
     * @throws RpcMethodNotFoundExceptionRpc
     */
    public function handle(RpcRequest $singleRequest): RpcResponse
    {
        $this->newRequest($singleRequest);
        // Resolved once: the cache needs the implementation for its key and to skip non-cacheable methods.
        try {
            $service = $this->serviceHolder->getService($singleRequest->getMethod(), $singleRequest->apiVersion);
        } catch (ServiceNotFoundException) {
            $service = null;
        }
        try {
            if (!$service || ($singleRequest->getRpcParams()?->ignoreCache ?? false)) throw new WrongWayException();
            $response = $this->cache->getCacheResponse($singleRequest, $service);
        } catch (WrongWayException) {
            $response = $this->handleRpcRequest($singleRequest, $service);
        }
        if (!$singleRequest->hasError() && $service) {
            $this->cache->saveCacheResponse($singleRequest, $response, $service);
        }

        $singleRequest->setResponse($response);
        return $response;
    }
}