<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers;

use phpDocumentor\Reflection\DocBlock;
use ReflectionMethod;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;

use function is_array;
use function is_null;
use function is_string;

class ReturnFiller extends AbstractServiceFiller
{

    public function fill(ReflectionMethod $method, Service $service, DocBlock $methodDoc): void
    {
        $returnReflection = $method->getReturnType();
        $returns = [];
        if (is_null($returnReflection) && is_string($method->getDocComment())) {
            foreach ($methodDoc->getTagsByName('return') as $return) {
                $returns[] = (string)$return->getType();
            }
        } else {
            $returns = $this->getTypes($returnReflection);
        }
        $returnDesc = $this->getReturnDescription($methodDoc);
        if (is_array($returns)) {
            foreach ($returns as $type) {
                $service->addReturn($this->typeFrom($type), $returnDesc);
            }
        } else {
            $service->addReturn($this->typeFrom($returns), $returnDesc);
        }
    }

    protected function getReturnDescription(DocBlock $docBlock): string
    {
        $desc = '';
        /**
         * @var DocBlock\Tags\Return_ $return
         */
        foreach ($docBlock->getTagsByName('return') as $return) {
            $desc = $return->getDescription();
        }
        return $desc;

    }

}