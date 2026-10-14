<?php

namespace Ufo\JsonRpcBundle\Server\RequestPrepare;

use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcRequest;

use function substr;
use function trim;

trait BatchChecker
{
    protected function checkBatchRequest(string $content): bool
    {
        $firstChar = substr(trim($content), 0, 1);
        return ($firstChar === '[');
    }
    protected function prepareBatch(string $content, string $version): RpcBatchRequest
    {
        $batch = RpcBatchRequest::fromJson($content);
        if ($version === Info::DEFAULT_VERSION) {
            return $batch;
        }
        $versioned = new RpcBatchRequest();
        foreach ($batch->getCollection() as $request) {
            $versioned->addRequestObject(RpcRequest::fromJson((string)$request->getRawJson(), $version));
        }
        return $versioned;
    }

}