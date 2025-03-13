<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers;

use phpDocumentor\Reflection\DocBlock;
use ReflectionMethod;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RPC\Cache;

class CacheFiller extends AbstractServiceFiller
{
    public function fill(ReflectionMethod $method, Service $service, DocBlock $methodDoc): void
    {
        $this->getAttribute($method, $service, Cache::class, 'setCacheInfo');
    }
}