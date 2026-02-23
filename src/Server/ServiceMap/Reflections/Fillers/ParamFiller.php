<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers;

use phpDocumentor\Reflection\DocBlock;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\DTO\Helpers\TypeHintResolver as T;
use Ufo\RpcObject\RPC\Param;
use Ufo\DTO\Helpers\EnumResolver;

use function is_string;

#[AutoconfigureTag(IServiceFiller::TAG, ['priority' => 101])]
class ParamFiller extends AbstractServiceFiller
{
    public function __construct(
        protected ChainParamConvertor $convertor,
    ) {}

    public function fill(ReflectionMethod $method, Service $service, DocBlock $methodDoc): void
    {
        $paramsReflection = $method->getParameters();
        if (!empty($paramsReflection)) {
            foreach ($paramsReflection as $paramRef) {
                $descType = $this->getParamType($methodDoc, $paramRef->getName());

                $type = (string) $paramRef->getType();
                $schema = T::typeDescriptionToJsonSchema($descType ?? $type, $service->uses);
                $t = EnumResolver::getEnumFQCN($descType ?? $type);
                $paramDefinition = ParamDefinition::fromParamReflection(
                    paramRef: $paramRef,
                    type: $schema,
                    realType: $this->getTypes($paramRef->getType()),
                    description: $this->getParamDescription($methodDoc, $paramRef->getName()),
                    paramItems: $descType
                );
                if (
                    !$paramDefinition->attributesCollection->getAttribute(Param::class)
                    && is_string($paramDefinition->getRealType())
                    && $this->convertor->supported($paramDefinition->getRealType())
                    && $paramAttr = $this->convertor->getParamAttr($paramDefinition->getRealType())
                ) {
                    $paramDefinition->attributesCollection->addAttribute($paramAttr);
                }
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
