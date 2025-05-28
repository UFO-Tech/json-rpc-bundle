<?php

namespace Ufo\JsonRpcBundle\Server\RequestPrepare;

use Ufo\RpcError\RpcJsonParseException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcRequest;

class RpcFromCli implements IRpcRequestCarrier
{
    use BatchChecker;

    protected ?RpcRequest $requestObject = null;

    protected ?RpcBatchRequest $batchRequest = null;

    /**
     * @throws RpcJsonParseException
     */
    public function __construct(
        protected string $inputJson
    )
    {
        $this->prepare();
    }

    /**
     * @throws RpcJsonParseException
     */
    protected function prepare(): void
    {
        if ($this->checkBatchRequest($this->inputJson)) {
            $this->batchRequest = RpcBatchRequest::fromJson($this->inputJson);
        } else {
            $this->requestObject = RpcRequest::fromJson($this->inputJson);
        }
    }
    
    public function getRequestObject(): RpcRequest
    {
        return $this->requestObject ?? throw new WrongWayException('Request is not set');
    }

    public function getBatchRequestObject(): RpcBatchRequest
    {
        return $this->batchRequest ?? throw new WrongWayException();
    }

}
