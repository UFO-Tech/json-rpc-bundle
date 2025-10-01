<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers;

use phpDocumentor\Reflection\DocBlock;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\DTO\Helpers\TypeHintResolver as T;

#[AutoconfigureTag(IServiceFiller::TAG, ['priority' => 101])]
class ParamFiller extends AbstractServiceFiller
{

    public function fill(ReflectionMethod $method, Service $service, DocBlock $methodDoc): void
    {
        $paramsReflection = $method->getParameters();
        if (!empty($paramsReflection)) {
            foreach ($paramsReflection as $paramRef) {
                $descType = $this->getParamType($methodDoc, $paramRef->getName());

                $type =  (string) $paramRef->getType();
                $schema = T::typeDescriptionToJsonSchema($descType ?? $type, $service->uses);

                $paramDefinition = ParamDefinition::fromParamReflection(
                    $paramRef,
                    $schema,
                    $this->getTypes($paramRef->getType()),
                    $this->getParamDescription($methodDoc, $paramRef->getName()),
                    $descType
                );
                try {
                    $paramDefinition->setDefault($paramRef->getDefaultValue());
                } catch (ReflectionException) {}
                $service->addParam($paramDefinition);
            }
        }
    }

    protected function getParamDescription(DocBlock $docBlock, string $paramName): string
    {
        $desc = '';
        /**
         * @var DocBlock\Tags\Param $param
         */
        foreach ($docBlock->getTagsByName('param') as $param) {
            if (!($param->getVariableName() === $paramName)) continue;

            if ($param->getDescription()) {
                $desc = $param->getDescription()->getBodyTemplate();
            }
            break;
        }

        return $desc;
    }

    protected function getParamType(DocBlock $docBlock, string $paramName): ?string
    {
        $_type = null;
        /**
         * @var DocBlock\Tags\Param $param
         */
        foreach ($docBlock->getTagsByName('param') as $param) {
            if (!($param->getVariableName() === $paramName)) {
                continue;
            }
            $_type = (string) $param->getType();
            break;
        }

        return $_type;
    }
}