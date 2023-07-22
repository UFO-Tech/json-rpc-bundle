<?php

namespace Ufo\JsonRpcBundle\Server;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcError\RpcJsonParseException;
use Ufo\RpcError\WrongWayException;
use Ufo\JsonRpcBundle\Interfaces\IFacadeRpcServer;
use Ufo\JsonRpcBundle\Serializer\RpcErrorNormalizer;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;
use Ufo\JsonRpcBundle\Server\Async\RpcCallbackProcessor;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcError;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

class RpcRequestHandler
{
    protected Request $request;
    
    protected bool $isBatchRequest = false;
    protected RpcBatchRequest $batchRequest;
    protected RpcRequest $requestObject;
    
    public function __construct(
        protected IFacadeRpcServer    $rpcServerFacade,
        protected SerializerInterface $serializer,
        protected RpcAsyncProcessor $asyncProcessor,
        protected RpcCallbackProcessor $callbackProcessor,
    )
    {
    }

    public function handle(Request $request, bool $json = false): array|string
    {
        $this->request = $request;
        
        try {
            $result = $this->handlePost();
        } catch (WrongWayException) {
            $result = $this->handleGet();
        }
        return $json ? $this->serializer->serialize($result, 'json') : $result;
    }

    protected function handleGet(): array
    {
        return $this->rpcServerFacade->getServiceMap()->toArray();
    }

    /**
     * @return array
     * @throws RpcJsonParseException
     * @throws WrongWayException
     */
    protected function handlePost(): array
    {
        if (!$this->isPost()) {
            throw new WrongWayException();
        }
        return $this->checkBatchRequest()
            ->createRequestObject()
            ->smartHandle()
        ;
    }

    protected function processQueue(array &$queue, ?\Closure $callback)
    {
        foreach ($queue as $key => &$singleRequest) {
            /**
             * @var RpcRequest $singleRequest
             */
            $singleRequest->refreshRawJson($this->serializer);
            $this->asyncProcessor->createProcesses(
                $singleRequest,
                $this->rpcServerFacade->getSecurity()->getToken(),
                timeout: $singleRequest->getRpcParams()->getTimeout()
            );
            unset($queue[$key]);
        }

        $this->asyncProcessor->process($callback);
    }

    protected function closureSetResponse(): \Closure
    {
        $self = $this;
         return function (string $output, RpcRequest $request) use ($self) {
             $batchRequest = $self->batchRequest;

             try {
                 if (empty($output)) {
                     throw new RpcAsyncRequestException(
                         'The async process did not return any results. Try increasing the timeout by adding the "$rpc.timeout" parameter on params request'
                     );
                 }
                 /**
                  * @var RpcResponse $response
                  */
                 $response = $self->serializer->deserialize($output, RpcResponse::class, 'json');
             } catch (\Throwable $e) {
                 if ($e instanceof AbstractRpcErrorException) {
                     $error = new RpcError($e->getCode(), $e->getMessage(), $e);
                 } else {
                     $error = new RpcError(
                         AbstractRpcErrorException::DEFAULT_CODE,
                         'Uncatch async error',
                         $e
                     );
                 }
                 $response = new RpcResponse(
                     id: $request->getId(),
                     error: $error,
                     version: $request->getVersion(),
                     requestObject: $request
                 );
             }
             $result = $self->serializer->normalize($response, context: [AbstractNormalizer::GROUPS => [$response->getResponseSignature()]]);
             $batchRequest->addResult($result);

            if ($batchRequest->getReadyToHandle()) {
                $self->processQueue($batchRequest->getReadyToHandle(), $self->closureSetResponse());
            }
        };
}
    protected function smartHandle(): array
    {
        if ($this->isBatchRequest) {
            $batchRequest = $this->batchRequest;
            $this->processQueue(
                $batchRequest->getReadyToHandle(),
                $this->closureSetResponse()
            );

            foreach ($batchRequest->provideUnprocessedRequests() as $key => $unprocessedRequest) {
                $batchRequest->addResult($this->provideSingleRequest($unprocessedRequest));
            }

            $result = $batchRequest->getResults(false);
        } else {
            $result = $this->provideSingleRequest($this->requestObject);
        }
        return $result;
    }

    public function provideSingleRequest(RpcRequest $singleRequest): array
    {
        $result = $this->provideSingleRequestToResponse($singleRequest);
        $context = [
            AbstractNormalizer::GROUPS => [$result->getResponseSignature()],
            RpcErrorNormalizer::RPC_CONTEXT => true,
        ];
        return $this->serializer->normalize($result, context: $context);
    }

    public function provideSingleRequestToResponse(RpcRequest $singleRequest): RpcResponse
    {
        $result = $this->rpcServerFacade->handle($singleRequest);
        if (!$singleRequest->hasError() && $singleRequest->isAsync()) {
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
                        'url' => (string)$singleRequest->getRpcParams()->getCallbackObject(),
                        'status' => $status,
                        'data' => $data
                    ]
                ],
                version: $singleRequest->getVersion(),
                requestObject: $singleRequest
            );
        }
        return $result;
    }

    protected function checkBatchRequest(): static
    {
        $firstChar = substr(trim($this->request->getContent()), 0, 1);
        if ($firstChar === '[') {
            $this->isBatchRequest = true;
        }
        return $this;
    }

    /**
     * @return $this
     * @throws RpcJsonParseException
     */
    protected function createRequestObject(): static
    {
        if ($this->isBatchRequest) {
            $this->batchRequest = RpcBatchRequest::fromJson($this->request->getContent());
        } else {
            $this->requestObject = RpcRequest::fromJson($this->request->getContent());
        }
        return $this;
    }

    public function isGet(): bool
    {
        return $this->checkMethod(Request::METHOD_GET);
    }

    public function isPost(): bool
    {
        return $this->checkMethod(Request::METHOD_POST);
    }

    protected function checkMethod(string $method): bool
    {
        return $method == $this->request->getMethod();
    }
}
