<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers;

use phpDocumentor\Reflection\DocBlock;
use ReflectionMethod;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcError\RpcInternalException;

interface IServiceFiller
{
    const string TAG = 'ufo-rpc.service-iterator';

    /**
     * @param ReflectionMethod $method
     * @param Service $service
     * @param DocBlock $methodDoc
     * @throws RpcInternalException
     * @return void
     */
    public function fill(ReflectionMethod $method, Service $service, DocBlock $methodDoc): void;
}