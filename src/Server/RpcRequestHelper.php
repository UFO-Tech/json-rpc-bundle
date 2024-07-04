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

class RpcRequestHelper
{
    protected bool $isBatchRequest = false;

    protected RpcBatchRequest $batchRequest;

    protected RpcRequest $requestObject;

    /**
     * @throws RpcJsonParseException
     */
    public function __construct(
        protected Request $request
    )
    {
        $this->checkBatchRequest()->createRequestObject();
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

    /**
     * @return bool
     */
    public function isBatchRequest(): bool
    {
        return $this->isBatchRequest;
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

    /**
     * @return RpcRequest|RpcBatchRequest
     */
    public function getRequestObject(): RpcRequest|RpcBatchRequest
    {
        return $this->isBatchRequest ? $this->batchRequest : $this->requestObject;
    }

}
