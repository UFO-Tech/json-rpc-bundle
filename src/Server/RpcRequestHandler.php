<?php

namespace Ufo\JsonRpcBundle\Server;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Context\ContextBuilderInterface;
use Symfony\Component\Serializer\Context\Normalizer\ObjectNormalizerContextBuilder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\JsonRpcBundle\Serializer\RpcResponseContextBuilder;
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
    protected RpcRequestHelper $requestHelper;

    public function __construct(
        protected IFacadeRpcServer $rpcServerFacade,
        protected SerializerInterface $serializer,
        protected RpcAsyncProcessor $asyncProcessor,
        protected RpcCallbackProcessor $callbackProcessor,
        protected RpcResponseContextBuilder $contextBuilder
    ) {}

    public function handle(Request $request, bool $json = false): array|string
    {
        $this->request = $request;
        $this->requestHelper = new RpcRequestHelper($request);
        $this->rpcServerFacade->getSecurity()->isValidRequest();
        try {
            $result = $this->handlePost();
        } catch (WrongWayException) {
            $result = $this->handleGet();
        }

        return $json ? $this->serializer->serialize($result, 'json') : $result;
    }

    public function handleGet(): array
    {
        $this->rpcServerFacade->handleSmRequest();

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

        return $this->smartHandle();
    }

    protected function processQueue(array &$queue, ?\Closure $callback): void
    {
        foreach ($queue as $key => &$singleRequest) {
            /**
             * @var RpcRequest $singleRequest
             */
            $singleRequest->refreshRawJson($this->serializer);
            $this->asyncProcessor->createProcesses($singleRequest, $this->rpcServerFacade->getSecurity()->getToken(),
                timeout: $singleRequest->getRpcParams()->getTimeout());
            unset($queue[$key]);
        }
        $this->asyncProcessor->process($callback);
    }

    protected function closureSetResponse(): \Closure
    {
        return function (string $output, RpcRequest $request) {
            $batchRequest = $this->requestHelper->getRequestObject();
            try {
                if (empty($output)) {
                    throw new RpcAsyncRequestException('The async process did not return any results. Try increasing the timeout by adding the "$rpc.timeout" parameter on params request');
                }
                /**
                 * @var RpcResponse $response
                 */
                $response = $this->serializer->deserialize($output, RpcResponse::class, 'json');
            } catch (\Throwable $e) {
                if ($e instanceof AbstractRpcErrorException) {
                    $error = new RpcError($e->getCode(), $e->getMessage(), $e);
                } else {
                    $error = new RpcError(AbstractRpcErrorException::DEFAULT_CODE, 'Uncatched async error', $e);
                }
                $response = new RpcResponse(
                    id: $request->getId(),
                    error: $error,
                    version: $request->getVersion(),
                    requestObject: $request,
                    contextBuilder: $this->contextBuilder
                );
            }
            $result = $this->serializer->normalize($response,
                context: [AbstractNormalizer::GROUPS => [$response->getResponseSignature()]]);
            $batchRequest->addResult($result);
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
