<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers;

use phpDocumentor\Reflection\DocBlock;
use ReflectionMethod;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;

class DescriptionFiller extends AbstractServiceFiller
{
    public function fill(ReflectionMethod $method, Service $service, DocBlock $methodDoc): void
    {
        $service->setDescription($methodDoc->getSummary());

        if ($methodDoc->getTagsByName('deprecated')) {
            $service->setDeprecated();
        }
    }

}