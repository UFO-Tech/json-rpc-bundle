<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections;

use ReflectionProperty;
use ReflectionType;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcObject\RPC\ResultAsDTO;

use function class_exists;
use function count;
use function implode;

class ResultAsDtoReflector
{

    protected \ReflectionClass $refDTO;

    protected array $responseFormat = [];

    /**
     * @throws RpcInternalException
     */
    public function __construct(
        public readonly ResultAsDTO $resultAsDto,
    )
    {
        $this->reflect();
        $this->parse();
        $this->setResult();
    }

    protected function setResult(): void
    {
        $refAttr = new \ReflectionObject($this->resultAsDto);
        $refAttr->getProperty('dtoFormat')->setValue($this->resultAsDto, $this->responseFormat);
    }

    protected function reflect(): void
    {
        if (!class_exists($this->resultAsDto->dtoFQCN)) {
            throw new RpcInternalException('Class "' . $this->resultAsDto->dtoFQCN . '" is not found');
        }
        $this->refDTO = new \ReflectionClass($this->resultAsDto->dtoFQCN);
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

    protected function getType(ReflectionProperty $property, ReflectionType $type): string
    {
        $typeName = $type->getName();
        if (count($property->getAttributes(ResultAsDTO::class)) > 0) {
            /** @var ResultAsDTO $resAsDto */
            $resAsDto = $property->getAttributes(ResultAsDTO::class)[0]->newInstance();
            new self($resAsDto);
            if ($typeName === 'array' && $resAsDto->collection) {
                $typeName = 'collection';
                $this->responseFormat['$collections'][$property->getName()] = $resAsDto;
            }
        }
        return $typeName;
    }
}