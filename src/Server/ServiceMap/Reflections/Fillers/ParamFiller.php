<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers;

use phpDocumentor\Reflection\DocBlock;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\DtoReflector;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Validations\JsonSchema\JsonSchemaPropertyNormalizer;
use Ufo\RpcError\RpcInternalException;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\RpcObject\RPC\Assertions;
use Ufo\RpcObject\RPC\DTO;
use Ufo\RpcObject\RPC\Param;

use function array_map;
use function class_exists;

#[AutoconfigureTag(IServiceFiller::TAG, ['priority' => 100])]
class ParamFiller extends AbstractServiceFiller
{

    public function __construct(
        protected ChainParamConvertor $paramConvertor,
        protected JsonSchemaPropertyNormalizer $jsonSchemaPropertyNormalizer
    ) {}

    public function fill(ReflectionMethod $method, Service $service, DocBlock $methodDoc): void
    {
        $paramsReflection = $method->getParameters();
        if (!empty($paramsReflection)) {
            foreach ($paramsReflection as $paramRef) {

                $type = $this->getTypes($paramRef->getType());
                $paramDefinition = ParamDefinition::fromParamReflection(
                    $paramRef,
                    $type,
                    $this->getParamDescription($methodDoc, $paramRef->getName()),
                    $this->getParamType($methodDoc, $paramRef->getName()),
                );
                try {
                    $paramDefinition->setDefault($paramRef->getDefaultValue());
                } catch (ReflectionException) {}
                $paramDefinition = $this->checkAttributes($paramDefinition, $service);
                $service->addParam($paramDefinition);
            }
        }
    }

    /**
     * @throws RpcInternalException
     */
    protected function checkAttributes(ParamDefinition $paramDefinition, Service $service): ParamDefinition
    {
        /**
         * @var Param $paramAttr
         */
        $paramAttr = $paramDefinition->getAttributesCollection()->getAttribute(Param::class);
        $assertionsAttr = $paramDefinition->getAttributesCollection()->getAttribute(Assertions::class);

        if ($paramAttr) {
            $assertionsAttr = $assertionsAttr ?? new Assertions([]);

            $paramDefinition->setSchema(
                $this->jsonSchemaPropertyNormalizer->normalize(
                    $assertionsAttr,
                    context: ['type' => $paramAttr->getType()]
                )
            )->setDefault($paramAttr->default);

            $paramDefinition = $paramDefinition->changeType($paramAttr->getType());
            $paramDefinition->getAttributesCollection()
                            ->addAttribute($paramAttr)
                            ->addAttribute($assertionsAttr)
            ;
        }
        $this->checkDTO($paramDefinition->getType(), $paramDefinition->name, $service);
        return $paramDefinition;
    }

    /**
     * @throws RpcInternalException
     */
    protected function checkDTO(string|array $type, string $paramName, Service $service): void
    {
        if (is_array($type)) {
            array_map(fn(string $type) => $this->checkDTO($type, $paramName, $service), $type);
            return;
        }
        $nType = TypeHintResolver::normalize($type);
        if ($nType === TypeHintResolver::OBJECT->value && class_exists($type)) {
            $service->addParamsDto($paramName, new DtoReflector(new DTO($type), $this->paramConvertor));
        }
    }

    protected function getParamDescription(DocBlock $docBlock, string $paramName): string
    {
        $desc = '';
        /**
         * @var DocBlock\Tags\Param $param
         */
        foreach ($docBlock->getTagsByName('param') as $param) {
            if (!($param->getVariableName() === $paramName)) {
                continue;
            }
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