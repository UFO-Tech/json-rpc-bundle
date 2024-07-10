<?php

namespace Ufo\JsonRpcBundle\Server;

use Psr\Log\LoggerInterface;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use TypeError;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Exceptions\EventCreateException;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\JsonRpcBundle\Serializer\RpcResponseContextBuilder;
use Ufo\JsonRpcBundle\Server\RpcCache\RpcCacheService;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcObject\Events\RpcErrorEvent;
use Ufo\RpcObject\Events\RpcEvent;
use Ufo\RpcObject\Events\RpcPostExecuteEvent;
use Ufo\RpcObject\Events\RpcPreExecuteEvent;
use Ufo\RpcObject\Events\RpcResponseEvent;
use Ufo\RpcObject\Rules\Validator\RpcValidator;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\UfoReflectionProcedure;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceLocator;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RpcError;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

use function call_user_func_array;
use function implode;
use function is_array;

class RpcServer
{
    const string VERSION_1 = '1.0';
    const string VERSION_2 = '2.0';

    protected ?RpcRequest $requestObject = null;
    protected RpcResponse $responseObject;

    public function __construct(
        protected ServiceMap $serviceMap,
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
            $service = $this->serviceMap->getService($method);
        } catch (ServiceNotFoundException $e) {
            throw new RpcMethodNotFoundExceptionRpc($e->getMessage());
        }

        $this->eventFactory->addListener(
            RpcEvent::RESPONSE,
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
        return function (RpcResponseEvent $event) {
            $this->responseObject = $event->response;
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