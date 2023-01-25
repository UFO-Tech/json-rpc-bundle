<?php

namespace Ufo\JsonRpcBundle\Server;

class RpcRequestRequireParamFromResponse
{

    public function __construct(protected string $responseId, protected string $responseFieldName)
    {
    }

    /**
     * @return string
     */
    public function getResponseId(): string
    {
        return $this->responseId;
    }

    /**
     * @return string
     */
    public function getResponseFieldName(): string
    {
        return $this->responseFieldName;
    }
    
}
