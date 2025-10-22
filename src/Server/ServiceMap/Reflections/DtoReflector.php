<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections;

use LogicException;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use ReflectionType;
use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcObject\RPC\Assertions;
use Ufo\RpcObject\RPC\DTO;
use Ufo\RpcObject\RPC\Param;

use function array_key_exists;
use function class_exists;
use function count;
use function current;
use function implode;

class DtoReflector
{
    const int DEPTH_SIZE = 3;

    protected ReflectionClass $refDTO;

    protected array $responseFormat = [];
    protected array $realFormat = [];

    /**
     * @throws RpcInternalException
     */
    public function __construct(
        public readonly DTO $dto,
        ChainParamConvertor $paramConvertor,
        protected int $depth = 0
    )
    {
        $this->reflect();
        $this->parse($this->depth, $paramConvertor);
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
        $this->refDTO = new ReflectionClass($this->dto->dtoFQCN);
    }

    protected function parse(int $depth, ChainParamConvertor $paramConvertor): void
    {
        if ($depth >= static::DEPTH_SIZE) return;

        $this->responseFormat['$dto'] = $this->refDTO->getShortName();
        $this->responseFormat['$uses'] = TypeHintResolver::getUsesNamespaces($this->dto->dtoFQCN);
        $refConstructor = $this->refDTO->getConstructor();
        $defaultParams = [];
        $requiredParams = [];
        if ($refConstructor) {
            foreach ($refConstructor->getParameters() as $param) {
                if (!$param->isPromoted()) continue;
                try {
                    $defaultParams[$param->getName()] = $param->getDefaultValue();
                } catch (\Throwable) {
                    $requiredParams[$param->getName()] = $param->getName();
                }
            }
        }

        foreach ($this->refDTO->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if (!isset($requiredParams[$property->getName()]) && $property->hasDefaultValue()) {
                $defaultParams[$property->getName()] = $property->getDefaultValue() ?? $defaultParams[$property->getName()] ?? null;
            }
            if (!array_key_exists($property->getName(), $defaultParams)) {
                $requiredParams[$property->getName()] = $property->getName();
            }
            $nullable = ($property->getType()->allowsNull()) ? '?' : '';
            try {
                $this->responseFormat[$property->getName()] = $nullable . $this->getType($property, $property->getType(), $paramConvertor);
            } catch (\Throwable ) {
                $t = [];
                foreach ($property->getType()?->getTypes() as $type) {
                    $t[] = $this->getType($property, $type, $paramConvertor);
                }
                $this->responseFormat[$property->getName()] = implode('|', $t);
            }
        }
        $this->responseFormat['$defaultParams'] = $defaultParams;
        $this->responseFormat['$requiredParams'] = $requiredParams;
    }

    /**
     * @throws RpcInternalException
     */
    protected function getType(ReflectionProperty $property, ReflectionType $type, ChainParamConvertor $paramConvertor): string
    {
        /** @var DTO $dto */
        $typeName = $type->getName();
        $this->checkParamConverter($property, $typeName, $paramConvertor);
        if (count($property->getAttributes(DTO::class)) > 0) {
            $dto = $property->getAttributes(DTO::class)[0]->newInstance();
            new static($dto, $paramConvertor, $this->depth + 1);
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
        $ctor = (new ReflectionClass($property->getDeclaringClass()->getName()))->getConstructor();
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

    public function __serialize(): array
    {
        return DTOTransformer::toArray($this);
    }

    /**
     * @throws ReflectionException|LogicException
     */
    public function __unserialize(array $data): void
    {
        $dtoArray = $data['dto'] ?? [];

        if (empty($dtoArray)) {
            throw new LogicException('Corrupted serialization payload: dto missing.');
        }

        $this->dto = DTOTransformer::fromArray(DTO::class, $dtoArray);
        $this->refDTO = new ReflectionClass($this->dto->dtoFQCN);
        $this->responseFormat = (array)($data['responseFormat'] ?? []);
        $this->realFormat     = (array)($data['realFormat']     ?? []);
        $this->depth          = (int)  ($data['depth']          ?? 0);
    }

    protected function checkParamConverter(ReflectionProperty $property, string &$typeName, ChainParamConvertor $paramConvertor): void
    {
        if ($paramAttrDef = current($property->getAttributes(Param::class))) {
            /**
             * @var Param $paramAttr
             */
            $paramAttr = $paramAttrDef->newInstance();
            $assertionsAttr = new Assertions([]);
            $responseFormat = [
                ...$this->responseFormat,
                ...$paramConvertor->jsonSchemaPropertyNormalizer->normalize(
                    $assertionsAttr,
                    context: ['type' => $paramAttr->getType()]

                )
            ];
            $this->realFormat[$property->getName()] = $typeName;
            $typeName = $responseFormat['type'] ?? $typeName;
        }
    }
}