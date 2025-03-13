<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers;

use phpDocumentor\Reflection\DocBlock;
use ReflectionMethod;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;

class ThrowsFiller extends AbstractServiceFiller
{

    public function fill(ReflectionMethod $method, Service $service, DocBlock $methodDoc): void
    {
        foreach ($methodDoc->getTagsByName('throws') as $throw) {
            $service->addThrow((string)$throw);
        }
    }

}