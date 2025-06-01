<?php

namespace Ufo\JsonRpcBundle\Server\RequestPrepare;

use Symfony\Component\HttpFoundation\Request;
use Ufo\RpcError\RpcJsonParseException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcRequest;

class RpcFromHttp implements IRpcRequestCarrier
{
    use BatchChecker;

    protected ?RpcBatchRequest $batchRequest = null;

    protected ?RpcRequest $requestObject = null;

    /**
     * @throws RpcJsonParseException
     */
    public function __construct(
        protected Request $request
    )
    {
        $this->prepare();
    }

    /**
     * @throws RpcJsonParseException
     */
    protected function prepare(): void
    {
        if ($this->checkBatchRequest($this->request->getContent())) {
            $this->batchRequest = RpcBatchRequest::fromJson($this->request->getContent());
        } else {
            $this->requestObject = RpcRequest::fromJson($this->request->getContent());
        }
    }

    public function getRequestObject(): RpcRequest
    {
        return $this->requestObject ?? throw new WrongWayException();
    }

    public function getBatchRequestObject(): RpcBatchRequest
    {
        return $this->batchRequest ?? throw new WrongWayException();
    }

    public function getHttpRequest(): Request
    {
        return $this->request;
    }
}
