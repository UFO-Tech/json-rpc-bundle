<?php

namespace Ufo\JsonRpcBundle\Server;

use Closure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Serializer\RpcResponseContextBuilder;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceLocator;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcError\RpcJsonParseException;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\RpcError\WrongWayException;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;
use Ufo\JsonRpcBundle\Server\Async\RpcCallbackProcessor;
use Ufo\RpcObject\Events\RpcAsyncOutputEvent;
use Ufo\RpcObject\Events\RpcEvent;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;


class RpcRequestHandler
{
    protected Request $request;
    protected RpcRequestHelper $requestHelper;

    protected null|array|string $response = null;

    public function __construct(
        protected RpcServer $rpcServer,
        protected SerializerInterface $serializer,
        protected RpcAsyncProcessor $asyncProcessor,
        protected RpcCallbackProcessor $callbackProcessor,
        protected RpcResponseContextBuilder $contextBuilder,
        protected RpcEventFactory $eventFactory,
        protected IRpcSecurity $rpcSecurity,
        protected ServiceLocator $serviceLocator,
    ) {}

    /**
     * @throws RpcJsonParseException
     */
    public function handle(Request $request, bool $json = false): array|string
    {
        $this->request = $request;
        $this->requestHelper = new RpcRequestHelper($request);

        try {
            $result = $this->handlePost();
        } catch (WrongWayException) {
            $result = $this->handleGet();
        }

        return $json ? $this->serializer->serialize($result, 'json') : $result;
    }

    public function handleGet(): array
    {
        return $this->serviceLocator->toArray();
    }

    /**
     * @return array
     * @throws WrongWayException
     */
    protected function handlePost(): array
    {
        if (!$this->isPost()) {
            throw new WrongWayException();
        }

        return $this->smartHandle();
    }

    protected function processQueue(array &$queue, ?Closure $callback): void
    {
        foreach ($queue as $key => &$singleRequest) {
            /**
             * @var RpcRequest $singleRequest
             */
            $singleRequest->refreshRawJson($this->serializer);
            $this->asyncProcessor->createProcesses($singleRequest, $this->rpcSecurity->getToken(),
                timeout: $singleRequest->getRpcParams()->getTimeout());
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
                $this->requestHelper->getRequestObject(),
                $output
            );
            $batchRequest = $event->batchRequest;
            if ($batchRequest->getReadyToHandle()) {
                $this->processQueue($batchRequest->getReadyToHandle(), $this->closureSetResponse());
            }
        };
    }

    protected function smartHandle(): array
    {
        if (($requestObj = $this->requestHelper->getRequestObject()) instanceof RpcBatchRequest) {
            $this->processQueue($requestObj->getReadyToHandle(), $this->closureSetResponse());
            foreach ($requestObj->provideUnprocessedRequests() as $key => $unprocessedRequest) {
                $requestObj->addResult($this->provideSingleRequest($unprocessedRequest));
            }
            $result = $requestObj->getResults(false);
        } else {
            $result = $this->provideSingleRequest($requestObj);
        }

        return $result;
    }

    public function provideSingleRequest(RpcRequest $singleRequest): array
    {
        $result = $this->provideSingleRequestToResponse($singleRequest);
        $context = $this->contextBuilder->withResponseSignature($result);
        return $this->serializer->normalize($result, context: $context->toArray());
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

    public function isGet(): bool
    {
        return $this->requestHelper->isGet();
    }

    public function isPost(): bool
    {
        return $this->requestHelper->isPost();
    }
}
