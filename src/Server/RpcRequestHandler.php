<?php

namespace Ufo\JsonRpcBundle\Server;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Exceptions\BatchIsDisabledException;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\JsonRpcBundle\ConfigService\RpcBatchConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Server\Batch\IBatchStrategy;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcError\RpcBadRequestException;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;
use Ufo\JsonRpcBundle\Server\Async\RpcCallbackProcessor;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\RpcError\RpcTokenNotSentException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RpcNotificationRequest;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Transformer\RpcResponseContextBuilder;

use function in_array;

class RpcRequestHandler
{
    protected const array SUPPORTED_VERSIONS = ['2.0'];

    protected Request $request;

    protected null|array|string $response = null;

    public function __construct(
        protected RequestCarrier $requestCarrier,
        protected RpcServer $rpcServer,
        protected SerializerInterface&NormalizerInterface $serializer,
        protected RpcAsyncProcessor $asyncProcessor,
        protected RpcCallbackProcessor $callbackProcessor,
        protected RpcResponseContextBuilder $contextBuilder,
        protected RpcEventFactory $eventFactory,
        protected IRpcSecurity $rpcSecurity,
        protected RpcMainConfig $rpcConfig,
        /** @var iterable<IBatchStrategy> */
        #[AutowireIterator(IBatchStrategy::TAG)]
        protected iterable $batchStrategies = [],
    ) {}

    /**
     * @return array
     * @throws RpcAsyncRequestException
     * @throws RpcBadRequestException
     * @throws RpcMethodNotFoundExceptionRpc
     * @throws RpcRuntimeException
     * @throws RpcTokenNotSentException
     * @throws ServiceNotFoundException
     * @throws WrongWayException
     * @throws WrongWayException|RpcInternalException
     */
    public function handle(): array
    {
        try {
            $requestObj = $this->requestCarrier->getBatchRequestObject();
            $batchConfig = $this->rpcConfig->batchConfig;
            if (!$batchConfig->enabled) {
                throw new BatchIsDisabledException();
            }
            if (empty($requestObj->getCollection()))
                throw new RpcBadRequestException('Can`t process empty batch request');
            $this->batchStrategy($batchConfig)->handle($requestObj, $this);
            foreach ($requestObj->provideUnprocessedRequests() as $unprocessedRequest) {
                // A notification whose dependency failed is neither executed nor answered.
                if ($unprocessedRequest instanceof RpcNotificationRequest) continue;
                $response = $this->provideSingleRequestToResponse($unprocessedRequest);
                $requestObj->addResponse($response, $this->responseToArray($response));
            }
            $result = $requestObj->getResults(false);
        } catch (WrongWayException $e) {
            $requestObj = $this->requestCarrier->getRequestObject();
            $result = $this->provideSingleRequest($requestObj);
        }

        return $result;
    }

    /**
     * Picks the batch execution strategy for batch.process (process or inline).
     *
     * @throws RpcInternalException
     */
    protected function batchStrategy(RpcBatchConfig $config): IBatchStrategy
    {
        foreach ($this->batchStrategies as $strategy) {
            if ($strategy->supports($config)) {
                return $strategy;
            }
        }
        throw new RpcInternalException('No batch strategy supports the configured batch mode');
    }

    /**
     * @param RpcRequest $singleRequest
     * @return array
     * @throws RpcBadRequestException
     * @throws RpcMethodNotFoundExceptionRpc
     */
    public function provideSingleRequest(RpcRequest $singleRequest): array
    {
        $result = $this->provideSingleRequestToResponse($singleRequest);
        return match (true) {
            $singleRequest instanceof RpcNotificationRequest => [],
            default => $this->responseToArray($result)
        };
    }

    public function responseToArray(RpcResponse $response): array
    {
        $context = $this->contextBuilder->withResponseSignature($response);
        return $this->serializer->normalize($response, context: $context->toArray());
    }

    /**
     * @param RpcRequest $singleRequest
     * @return RpcResponse
     * @throws RpcBadRequestException
     * @throws RpcMethodNotFoundExceptionRpc
     */
    public function provideSingleRequestToResponse(RpcRequest $singleRequest): RpcResponse
    {
        if (!in_array($singleRequest->getVersion(), static::SUPPORTED_VERSIONS, true)) {
            throw new RpcBadRequestException('Unsupported jsonrpc protocol version');
        }
        $this->eventFactory->fireRequest($singleRequest);

        return match (true) {
            $singleRequest->isAsync() => $this->handleCallback($singleRequest),
            $singleRequest instanceof RpcNotificationRequest => $this->handleNotification($singleRequest),
            default => $this->rpcServer->handle($singleRequest)
        };
    }

    protected function handleCallback(RpcRequest $singleRequest): RpcResponse
    {
        if ($singleRequest instanceof RpcNotificationRequest) {
            return $this->handleNotification($singleRequest);
        }

        return $this->firePreResponseEvent(
            $singleRequest,
            [
                'async' => true,
                'callback' => (string)$singleRequest->getRpcParams()->getCallbackObject(),
            ]
        );
    }

    protected function handleNotification(RpcNotificationRequest $singleRequest): RpcResponse
    {
        return $this->firePreResponseEvent($singleRequest, null);
    }

    protected function firePreResponseEvent(RpcRequest $singleRequest, ?array $result): RpcResponse
    {
        $response = new RpcResponse(
            $singleRequest->getId(),
            $result,
            version: $singleRequest->getVersion(),
            requestObject: $singleRequest,
            contextBuilder: $this->contextBuilder
        );

        $singleRequest->setResponse($response);
        $service = $this->rpcServer->serviceHolder->getService($singleRequest->getMethod(), $singleRequest->apiVersion ?? Info::DEFAULT_VERSION);
        $this->eventFactory->fire(RpcEvent::PRE_RESPONSE,
            $response,
            $singleRequest,
            $service,
        );
        return $response;
    }
}
