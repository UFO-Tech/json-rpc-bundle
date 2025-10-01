<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers;

use phpDocumentor\Reflection\DocBlock;
use ReflectionMethod;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;

use function is_array;

class ReturnFiller extends AbstractServiceFiller
{

    public function fill(ReflectionMethod $method, Service $service, DocBlock $methodDoc): void
    {
        $returnReflection = $method->getReturnType();
        $returns = $this->getTypes($returnReflection);
        $service->setReturn($returns, $this->getReturnType($methodDoc), $this->getReturnDescription($methodDoc));
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

    protected function getReturnType(DocBlock $docBlock): ?string
    {
        $tags = $docBlock->getTagsByName('return');
        if (empty($tags)) {
            return null;
        }
        return (string) $tags[0]->getType();
    }

}