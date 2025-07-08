<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers;

use phpDocumentor\Reflection\DocBlock;
use ReflectionMethod;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use phpDocumentor\Reflection\TypeResolver;

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
                $typeString = $this->getReturnType($methodDoc, $this->typeFrom($type));
                $service->addReturn($this->typeFrom($type), $returnDesc, $typeString);
            }
        } else {
            $typeString = $this->getReturnType($methodDoc, $this->typeFrom($returns));
            $service->addReturn($this->typeFrom($returns), $returnDesc, $typeString);
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

    protected function getReturnType(DocBlock $docBlock, string $type): ?string
    {
        $tags = $docBlock->getTagsByName('return');
        if (empty($tags)) {
            return null;
        }
        return (string) $tags[0]->getType();
    }

}