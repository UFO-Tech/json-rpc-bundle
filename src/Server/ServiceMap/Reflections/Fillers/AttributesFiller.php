<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers;

use Deprecated;
use phpDocumentor\Reflection\DocBlock;
use ReflectionMethod;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RPC\CacheRelation;

class AttributesFiller extends AbstractServiceFiller
{
    public function fill(ReflectionMethod $method, Service $service, DocBlock $methodDoc): void
    {
        foreach ($method->getAttributes() as $attr) {
            $attrInstance = $attr->newInstance();

            $attrInstance = match (true) {
                $attrInstance instanceof CacheRelation
                    && !isset($attrInstance->serviceFQCN) => $this->cacheRelation($attrInstance, $service),
                $attrInstance instanceof Deprecated => $this->deprecated($attrInstance, $service),
                default => $attrInstance
            };
            $service->setAttribute($attrInstance);
        }
    }

    protected function cacheRelation(object $attr, Service $service): object
    {
        return $attr->cloneToClass($service->getProcedureFQCN());
    }

    protected function deprecated(object $attr, Service $service): object
    {
        $service->setDeprecated();
        return $attr;
    }

}