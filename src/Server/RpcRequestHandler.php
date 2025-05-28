<?php

namespace Ufo\JsonRpcBundle\Server;

use Closure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;
use Ufo\JsonRpcBundle\Server\Async\RpcCallbackProcessor;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcAsyncOutputEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Transformer\RpcResponseContextBuilder;


class RpcRequestHandler
{
    protected Request $request;

    protected null|array|string $response = null;

    public function __construct(
        protected RequestCarrier $requestCarrier,
        protected RpcServer $rpcServer,
        protected SerializerInterface $serializer,
        protected RpcAsyncProcessor $asyncProcessor,
        protected RpcCallbackProcessor $callbackProcessor,
        protected RpcResponseContextBuilder $contextBuilder,
        protected RpcEventFactory $eventFactory,
        protected IRpcSecurity $rpcSecurity,
    ) {}

    /**
     * @return array
     * @throws RpcAsyncRequestException
     * @throws RpcMethodNotFoundExceptionRpc
     * @throws RpcRuntimeException
     * @throws WrongWayException
     */
    public function handle(): array
    {
        try {
            $requestObj = $this->requestCarrier->getBatchRequestObject();
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
     * @throws RpcAsyncRequestException
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
     * @throws RpcRuntimeException
     * @throws RpcMethodNotFoundExceptionRpc
     */
    public function provideSingleRequest(RpcRequest $singleRequest): array
    {
        $result = $this->provideSingleRequestToResponse($singleRequest);
        return $this->responseToArray($result);
    }

    public function responseToArray(RpcResponse $response): array
    {
        $context = $this->contextBuilder->withResponseSignature($response);
        return $this->serializer->normalize($response, context: $context->toArray());
    }

    /**
     * @param RpcRequest $singleRequest
     * @return RpcResponse
     * @throws RpcRuntimeException
     * @throws RpcMethodNotFoundExceptionRpc
     */
    public function provideSingleRequestToResponse(RpcRequest $singleRequest): RpcResponse
    {
        $event = $this->eventFactory->fireRequest($singleRequest);
        $result = $this->rpcServer->handle($event->rpcRequest);

        if ($singleRequest->isAsync()) {
            try {
                $status = true;
                $data = [];
                $this->callbackProcessor->process($singleRequest);
            } catch (RpcAsyncRequestException $e) {
                $status = false;
                $data = $e;
            }
            $result = new RpcResponse(
                $singleRequest->getId(),
                [
                    'callback' => [
                        'url'    => (string)$singleRequest->getRpcParams()->getCallbackObject(),
                        'status' => $status,
                        'data'   => $data,
                    ],
                ],
                version: $singleRequest->getVersion(),
                requestObject: $singleRequest,
                contextBuilder: $this->contextBuilder
            );
        }

        return $result;
    }
}
