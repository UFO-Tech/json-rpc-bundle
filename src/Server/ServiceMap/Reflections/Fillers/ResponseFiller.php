<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers;

use phpDocumentor\Reflection\DocBlock;
use ReflectionMethod;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\DtoReflector;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcObject\RPC\ResultAsDTO;

class ResponseFiller extends AbstractServiceFiller
{
    /**
     * @throws RpcInternalException
     */
    public function fill(ReflectionMethod $method, Service $service, DocBlock $methodDoc): void
    {
        /** @var ResultAsDTO $responseInfo */
        if ($responseInfo = $this->getAttribute($method, $service, ResultAsDTO::class, 'setResponseInfo')) {
            new DtoReflector($responseInfo);
        }
    }
}