<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections;

use ReflectionProperty;
use ReflectionType;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcObject\RPC\DTO;

use function class_exists;
use function count;
use function get_class;
use function implode;

class DtoReflector
{

    protected \ReflectionClass $refDTO;

    protected array $responseFormat = [];

    /**
     * @throws RpcInternalException
     */
    public function __construct(
        public readonly DTO $dto,
    )
    {
        $this->reflect();
        $this->parse();
        $this->setResult();
    }

    protected function setResult(): void
    {
        $refAttr = new \ReflectionObject($this->dto);
        $refAttr->getProperty('dtoFormat')->setValue($this->dto, $this->responseFormat);
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
            } catch (\Throwable) {
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
        $attrFQCN = get_class($this->dto);
        if (count($property->getAttributes($attrFQCN)) > 0) {
            $dto = $property->getAttributes($attrFQCN)[0]->newInstance();
            new static($dto);
            if ($typeName === 'array' && $dto->collection) {
                $typeName = 'collection';
                $this->responseFormat['$collections'][$property->getName()] = $dto;
            }
        }
        return $typeName;
    }
}