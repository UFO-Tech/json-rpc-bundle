<?php

namespace Ufo\JsonRpcBundle\Server;

use Closure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcError\RpcBadRequestException;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;
use Ufo\JsonRpcBundle\Server\Async\RpcCallbackProcessor;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcAsyncOutputEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\RpcError\RpcTokenNotSentException;
use Ufo\RpcError\WrongWayException;
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
     * @throws WrongWayException
     */
    public function handle(): array
    {
        try {
            $requestObj = $this->requestCarrier->getBatchRequestObject();
            if (empty($requestObj->getCollection()))
                throw new RpcBadRequestException('Can`t process empty batch request');
            $this->processQueue($requestObj->getReadyToHandle(), $this->closureSetResponse());
            foreach ($requestObj->provideUnprocessedRequests() as $unprocessedRequest) {
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
     * @param array $queue
     * @param Closure|null $callback
     * @throws RpcAsyncRequestException
     * @throws RpcTokenNotSentException
     */
    protected function processQueue(array &$queue, ?Closure $callback): void
    {
        foreach ($queue as $key => &$singleRequest) {
            /**
             * @var RpcRequest $singleRequest
             */
            $singleRequest->refreshRawJson($this->serializer);
            $this->asyncProcessor->createProcesses(
                $singleRequest,
                tokenName: strtolower($this->rpcSecurity->getTokenHolder()->getTokenKey()),
                token: $this->rpcSecurity->getTokenHolder()->getToken(),
                timeout: $singleRequest->getRpcParams()->getTimeout()
            );
            unset($queue[$key]);
        }
        $this->asyncProcessor->process($callback);
    }

    protected function closureSetResponse(): Closure
    {
        return function (string $output, RpcRequest $request) {
            /** @var RpcAsyncOutputEvent $event */
            $event = $this->eventFactory->fire(
                RpcEvent::OUTPUT_ASYNC,
                $request,
                $this->requestCarrier->getBatchRequestObject(),
                $output
            );
            $batchRequest = $event->batchRequest;
            if ($batchRequest->getReadyToHandle()) {
                $this->processQueue($batchRequest->getReadyToHandle(), $this->closureSetResponse());
            }
        };
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
        $service = $this->rpcServer->serviceHolder->getService($singleRequest->getMethod());
        $this->eventFactory->fire(RpcEvent::PRE_RESPONSE,
            $response,
            $singleRequest,
            $service,
        );
        return $response;
    }
}
