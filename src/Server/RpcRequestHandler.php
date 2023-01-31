<?php

namespace Ufo\JsonRpcBundle\Server;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcError\RpcBadRequestException;
use Ufo\RpcError\RpcJsonParseException;
use Ufo\RpcError\WrongWayException;
use Ufo\JsonRpcBundle\Interfaces\IFacadeRpcServer;
use Ufo\JsonRpcBundle\Serializer\RpcErrorNormalizer;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;
use Ufo\JsonRpcBundle\Server\Async\RpcCallbackProcessor;

class RpcRequestHandler
{
    protected Request $request;
    
    protected bool $isBatchRequest = false;
    protected RpcBatchRequestObject $batchRequestObject;
    protected RpcRequestObject $requestObject;
    
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
             * @var RpcRequestObject $singleRequest
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
         return function (string $output, RpcRequestObject $requestObject) use ($self) {
             $batchRequestObject = $self->batchRequestObject;

             try {
                 if (empty($output)) {
                     throw new RpcAsyncRequestException(
                         'The async process did not return any results. Try increasing the timeout by adding the "$rpc.timeout" parameter on params request'
                     );
                 }
                 /**
                  * @var RpcResponseObject $response
                  */
                 $response = $self->serializer->deserialize($output, RpcResponseObject::class, 'json');
             } catch (\Throwable $e) {
                 if ($e instanceof AbstractRpcErrorException) {
                     $error = new RpcErrorObject($e->getCode(), $e->getMessage(), $e);
                 } else {
                     $error = new RpcErrorObject(
                         AbstractRpcErrorException::DEFAULT_CODE,
                         'Uncatch async error',
                         $e
                     );
                 }
                 $response = new RpcResponseObject(
                     id: $requestObject->getId(),
                     error: $error,
                     version: $requestObject->getVersion(),
                     requestObject: $requestObject
                 );
             }
             $result = $self->serializer->normalize($response, context: [AbstractNormalizer::GROUPS => [$response->getResponseSignature()]]);
             $batchRequestObject->addResult($result);

            if ($batchRequestObject->getReadyToHandle()) {
                $self->processQueue($batchRequestObject->getReadyToHandle(), $self->closureSetResponse());
            }
        };
}
    protected function smartHandle(): array
    {
        if ($this->isBatchRequest) {
            $batchRequestObject = $this->batchRequestObject;
            $this->processQueue(
                $batchRequestObject->getReadyToHandle(),
                $this->closureSetResponse()
            );

            foreach ($batchRequestObject->provideUnprocessedRequests() as $key => $unprocessedRequest) {
                $batchRequestObject->addResult($this->provideSingleRequest($unprocessedRequest));
            }

            $result = $batchRequestObject->getResults(false);
        } else {
            $result = $this->provideSingleRequest($this->requestObject);
        }
        return $result;
    }

    public function provideSingleRequest(RpcRequestObject $singleRequest): array
    {
        $result = $this->provideSingleRequestObjectResponse($singleRequest);
        $context = [
            AbstractNormalizer::GROUPS => [$result->getResponseSignature()],
            RpcErrorNormalizer::RPC_CONTEXT => true,
        ];
        return $this->serializer->normalize($result, context: $context);
    }

    public function provideSingleRequestObjectResponse(RpcRequestObject $singleRequest): RpcResponseObject
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
            $result = new RpcResponseObject(
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
        $firstChar = substr($this->request->getContent(), 0, 1);
        if ($firstChar === '[') {
            $this->isBatchRequest = true;
        }
        return $this;
    }

    /**
     * @return $this
     * @throws RpcJsonParseException
     * @throws RpcBadRequestException
     */
    protected function createRequestObject(): static
    {
        if ($this->isBatchRequest) {
            $this->batchRequestObject = RpcBatchRequestObject::fromJson($this->request->getContent());
        } else {
            $this->requestObject = RpcRequestObject::fromJson($this->request->getContent());
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
