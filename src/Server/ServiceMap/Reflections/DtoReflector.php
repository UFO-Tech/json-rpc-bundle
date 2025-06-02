<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections;

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

    protected \ReflectionClass $refDTO;

    protected array $responseFormat = [];
    protected array $realFormat = [];

    /**
     * @throws RpcInternalException
     */
    public function __construct(
        public readonly DTO $dto,
        protected ChainParamConvertor $paramConvertor
    )
    {
        $this->reflect();
        $this->parse();
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

    protected function parse(): void
    {
        $ref = $this->refDTO;
        $this->responseFormat['$dto'] = $ref->getShortName();
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {

            $nullable = ($property->getType()->allowsNull()) ? '?' : '';
            try {
                $this->responseFormat[$property->getName()] = $nullable . $this->getType($property, $property->getType());
            } catch (\Throwable $t) {
                $t = [];
                foreach ($property->getType()->getTypes() as $type) {
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
        $attrFQCN = DTO::class;
        $this->checkParamConverter($property, $typeName);
        if (count($property->getAttributes($attrFQCN)) > 0) {
            $dto = $property->getAttributes($attrFQCN)[0]->newInstance();
            new static($dto, $this->paramConvertor);
            if ($typeName === 'array' && $dto->collection) {
                $typeName = TypeHintResolver::COLLECTION->value;
                $this->responseFormat['$collections'][$property->getName()] = $dto;
            }
        }
        return $typeName;
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