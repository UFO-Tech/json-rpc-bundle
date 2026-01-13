<?php

namespace Ufo\JsonRpcBundle\Server;

use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\JsonRpcBundle\Server\RpcCache\RpcCacheService;
use Ufo\JsonRpcBundle\Server\ServiceMap\IServiceHolder;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreResponseEvent;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

class RpcServer
{
    const string VERSION_1 = '1.0';
    const string VERSION_2 = '2.0';

    protected ?RpcRequest $requestObject = null;
    protected RpcResponse $responseObject;

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
    public function handleRpcRequest(RpcRequest $request): RpcResponse
    {
        $method = $request->getMethod();
        try {
            $service = $this->serviceHolder->getService($method);
        } catch (ServiceNotFoundException $e) {
            throw new RpcMethodNotFoundExceptionRpc($e->getMessage());
        }

        $this->eventFactory->addListener(
            RpcEvent::PRE_RESPONSE,
            $this->onRpcResponseCallback(),
            -100000
        );

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
        try {
            if ($singleRequest->getRpcParams()?->ignoreCache ?? false) throw new WrongWayException();
            $response = $this->cache->getCacheResponse($singleRequest);
        } catch (WrongWayException) {
            $response = $this->handleRpcRequest($singleRequest);
        }
        if (!$singleRequest->hasError()) {
            $this->cache->saveCacheResponse($singleRequest, $response);
        }

        $singleRequest->setResponse($response);
        return $response;
    }
}