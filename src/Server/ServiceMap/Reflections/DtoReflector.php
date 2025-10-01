<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections;

use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Types\ContextFactory;
use ReflectionException;
use ReflectionProperty;
use ReflectionType;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcObject\RPC\Assertions;
use Ufo\RpcObject\RPC\DTO;
use Ufo\RpcObject\RPC\Param;

use function class_exists;
use function count;
use function current;
use function get_class;
use function implode;

class DtoReflector
{
    const int DEPTH_SIZE = 3;

    protected \ReflectionClass $refDTO;

    protected array $responseFormat = [];
    protected array $realFormat = [];

    /**
     * @throws RpcInternalException
     */
    public function __construct(
        public readonly DTO $dto,
        protected ChainParamConvertor $paramConvertor,
        protected int $depth = 0
    )
    {
        $this->reflect();
        $this->parse($this->depth);
        $this->setResult();
    }

    /**
     * @throws ReflectionException
     */
    protected function setResult(): void
    {
        $refAttr = new \ReflectionObject($this->dto);
        $refAttr->getProperty('dtoFormat')->setValue($this->dto, $this->responseFormat);
        $refAttr->getProperty('realFormat')?->setValue($this->dto, $this->realFormat);
    }

    protected function reflect(): void
    {
        if (!class_exists($this->dto->dtoFQCN)) {
            throw new RpcInternalException('Class "' . $this->dto->dtoFQCN.'" is not found');
        }
        $this->refDTO = new \ReflectionClass($this->dto->dtoFQCN);
    }

    protected function parse(int $depth): void
    {
        if ($depth >= static::DEPTH_SIZE) return;

        $this->responseFormat['$dto'] = $this->refDTO->getShortName();
        $this->responseFormat['$uses'] = TypeHintResolver::getUsesNamespaces($this->dto->dtoFQCN);
        foreach ($this->refDTO->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {

            $nullable = ($property->getType()->allowsNull()) ? '?' : '';
            try {
                $this->responseFormat[$property->getName()] = $nullable . $this->getType($property, $property->getType());
            } catch (\Throwable $t) {
                $t = [];
                foreach ($property->getType()?->getTypes() as $type) {
                    $t[] = $this->getType($property, $type);
                }
                $this->responseFormat[$property->getName()] = implode('|', $t);
            }
        }
    }

    /**
     * @throws RpcInternalException
     */
    protected function getType(ReflectionProperty $property, ReflectionType $type): string
    {
        /** @var DTO $dto */
        $typeName = $type->getName();
        $this->checkParamConverter($property, $typeName);
        if (count($property->getAttributes(DTO::class)) > 0) {
            $dto = $property->getAttributes(DTO::class)[0]->newInstance();
            new static($dto, $this->paramConvertor, $this->depth + 1);
            if ($typeName === TypeHintResolver::ARRAY->value && $dto->isCollection()) {
                $typeName = TypeHintResolver::COLLECTION->value;
                $this->responseFormat['$collections'][$property->getName()] = $dto;
            }
        } else {
            try {
                $typeName = $this->getDescriptionType($property) ?? $typeName;
            } catch (\Throwable) {}
        }
        return $typeName;
    }

    protected function getDescriptionType(ReflectionProperty $property): ?string
    {
        $descType = null;
        $docProperty = $property->getDocComment();
        $ctor = (new \ReflectionClass($property->getDeclaringClass()->getName()))->getConstructor();
        $docConstructor = $ctor?->getDocComment();

        if (!$docProperty && $property->isPromoted() && $docConstructor) {
            
            $docReflection = DocBlockFactory::createInstance()->create($docConstructor);
            foreach ($docReflection->getTagsByName('param') as $param) {
                if ($param->getVariableName() !== $property->getName()) continue;
                $descType = (string)$param->getType();
                break;
            }
        }   
        if ($docProperty) {
            $docReflection = DocBlockFactory::createInstance()->create($docProperty);
            try {
                $descType = (string)$docReflection->getTagsByName('param')[0]?->getType();
            } catch (\Throwable) {
                $descType = (string)$docReflection->getTagsByName('var')[0]?->getType();
            }
        }

        return $descType;
    }

    protected function checkParamConverter(ReflectionProperty $property, string &$typeName): void
    {
        if ($paramAttrDef = current($property->getAttributes(Param::class))) {
            /**
             * @var Param $paramAttr
             */
            $paramAttr = $paramAttrDef->newInstance();
            $assertionsAttr = new Assertions([]);
            $responseFormat = [
                ...$this->responseFormat,
                ...$this->paramConvertor->jsonSchemaPropertyNormalizer->normalize(
                    $assertionsAttr,
                    context: ['type' => $paramAttr->getType()]

                )
            ];
            $this->realFormat[$property->getName()] = $typeName;
            $typeName = $responseFormat['type'] ?? $typeName;
        }
    }
}