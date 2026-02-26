<?php

namespace Ufo\JsonRpcBundle\DocAdapters\Traits;

use Ufo\DTO\Helpers\EnumResolver;
use Ufo\DTO\Helpers\TypeHintResolver as T;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\DtoReflector;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\DTO;

use function array_key_exists;
use function array_map;
use function class_exists;
use function implode;
use function is_array;
use function is_null;
use function stat;
use function str_starts_with;
use function substr;

trait JsonSchemaDtoFormatTrait
{
    const string SCHEMAS = '#/components/schemas/';
    protected array $schemas = [];

    public function checkAndGetSchemaFromDesc(array $objSchema, ?DTO $dtoAttr = null): array
    {
        if (($objSchema[T::TYPE] ?? '') === T::OBJECT->value
            && ($objSchema[T::CLASS_FQCN] ?? false)) {
            $dto = $this->createDTO($objSchema[T::CLASS_FQCN], $dtoAttr);
            return $this->schemaFromDto($dto->getFormat());
        }
        return $objSchema;
    }

    protected function replaceClassNameToDTO(array &$objSchema, array $uses = []): void
    {
        $type = $objSchema[T::TYPE] ?? '';
        if ($type === T::OBJECT->value && $class = ($objSchema[T::CLASS_FQCN] ?? false)) {
            $dto = $this->createDTO($class, null);
            $objSchema = $this->schemaFromDto($dto->getFormat());


        } elseif ($type === T::ARRAY->value && ($objSchema[T::ITEMS] ?? false)) {
            $this->replaceClassNameToDTO($objSchema[T::ITEMS], $uses);
        } elseif ($objSchema[T::ONE_OFF] ?? false) {
            foreach ($objSchema[T::ONE_OFF] as &$objSchema_) {
                $this->replaceClassNameToDTO($objSchema_);
            }
        } elseif ($objSchema[T::ADDITIONAL_PROPERTIES] ?? false) {
            $this->replaceClassNameToDTO($objSchema[T::ADDITIONAL_PROPERTIES]);
        }
    }

    /**
     * @throws RpcInternalException
     */
    public function schemaForParam(ParamDefinition $param, Service $service): array
    {
        $schema = $service->getSchema()['properties'] ?? [];
        $paramSchema = $schema[$param->name] ?? $param->getSchema() ?? [];

        if ($param->getRealType() === T::OBJECT->value
            && $dto = $this->checkParamHasDTO(
                $param->paramItems ?? '',
                $param->getRealType(),
                $service->uses,
                $param->getAttributesCollection()->getAttribute(DTO::class)
            )
        ) {
            $paramSchema = $this->schemaFromDto($dto->getFormat());
        } elseif ($param->getRealType() === T::ARRAY->value) {
            $newSchema = T::applyToSchema($paramSchema, fn(array $schema) => $this->checkAndGetSchemaFromDesc(
                $schema,
                $param->getAttributesCollection()->getAttribute(DTO::class)
            ));

            if (($newSchema[T::ITEMS] ?? false) && ($newSchema[T::ONE_OFF] ?? false)) {
                unset($paramSchema[T::ITEMS], $newSchema[T::ITEMS]);
            }

            $paramSchema = [...$paramSchema, ...$newSchema];

            if (($paramSchema[T::TYPE] ?? false)
                && ($paramSchema[T::TYPE] == T::OBJECT->value)
                && ($paramSchema[T::ADDITIONAL_PROPERTIES] ?? false)
            ) {
                unset($paramSchema[T::ITEMS]);
            }

            if (
                ($classFQCN = $newSchema[T::ITEMS][T::CLASS_FQCN]  ?? false)
                && $dto = $this->checkParamHasDTO(
                    $param->paramItems ?? '',
                    $classFQCN,
                    $service->uses,
                    $param->getAttributesCollection()->getAttribute(DTO::class)
                )
            ) {
                $paramSchema[T::ITEMS] = $this->schemaFromDto($dto->getFormat());
            }

        } elseif (is_array($param->getType())) {
            $paramSchema = T::applyToSchema(
                $param->getSchema(),
                fn(array $itemSchema) => $this->checkAndGetSchemaFromDesc($itemSchema)
            );
        }

        if ($enumFQCN = EnumResolver::getEnumFQCN($param->getRealType())) {
            $enumData = EnumResolver::generateEnumSchema($enumFQCN);
            $enumName = $enumData[EnumResolver::ENUM][EnumResolver::ENUM_NAME] ?? throw new \RuntimeException('Undefined enum name');
            $this->schemas[$enumName] = $enumData;
            $paramSchema = $this->applyEnumRefToSchema($paramSchema, $enumName, $enumData);
         }


        return $paramSchema;
    }

    public function checkParamHasDTO(string $type, string|array $realType, array $uses = [], ?DTO $dtoAttr = null): ?DTO
    {
        $dto = null;
        $class = null;
        if ($type) {
            $objSchema = T::typeDescriptionToJsonSchema($type, $uses);
            if ($objSchema[T::TYPE] ?? '' === T::OBJECT->value) {
                $class = $objSchema[T::CLASS_FQCN] ?? null;
            }
        }

        try {
            $class ??= $this->getRealObjectType($realType);
            $dto = $this->createDTO($class, $dtoAttr);
        } catch (WrongWayException) {}

        return $dto;
    }

    abstract protected function getParamConvertor(): ChainParamConvertor;

    protected function createDTO(string $classFQCN, ?DTO $dtoAttr): DTO
    {
        $dto = $dtoAttr ?? new DTO($classFQCN);
        new DtoReflector($dto, $this->getParamConvertor());
        return $dto;
    }

    /**
     * @throws RpcInternalException
     */
    public function schemaFromDto(array $format): array
    {
        $dtoName = $format['$dto'];
        try {
            $collections = array_map(
                function (DTO $res) {
                    return [
                        'schema' => $this->createSchemaLink($res->getFormat()['$dto']),
                        'format' => $res
                    ];
                }, $format['$collections'] ?? []
            );
        } catch (\Throwable) {}
        $uses = $format['$uses'] ?? [];
        unset($format['$dto']);
        unset($format['$collections']);
        unset($format['$uses']);
        $defaultParams = $format['$defaultParams'] ?? [];
        $requiredParams = $format['$requiredParams'] ?? [];
        unset($format['$defaultParams']);
        unset($format['$requiredParams']);

        $schemaLink = $this->createSchemaLink($dtoName);
        if (!isset($this->schemas[$dtoName])) {
            $this->schemas[$dtoName] = [];
            $schema = [
                T::TYPE => T::OBJECT->value,
                'properties' => [],
                'required' => []
            ];
            foreach ($format as $name => $value) {
                if ($requiredParams[$name] ?? false) {
                    $schema['required'][] = $name;
                }

                if (str_starts_with($value, '?')) {
                    $value = substr($value, 1) . '|null';
                }

                if (!$jsonValue = $this->detectDtoOnType($value, $collections[$name] ?? [])) {
                    $jsonValue = $this->detectArrayOfType($value, $uses);
                }
                $this->replaceClassNameToDTO(
                    $jsonValue,
                    $uses,
                );
                if (array_key_exists($name, $defaultParams)) {
                    $jsonValue['default'] = $defaultParams[$name];
                }

                $schema['properties'][$name] = $jsonValue;
            }
            $this->schemas[$dtoName] = $schema;
        }
        return $schemaLink;
    }

    /**
     * @param string|array $type
     * @return string
     * @throws WrongWayException
     */
    protected function getRealObjectType(string|array $type): string
    {
        if (is_array($type)) {
            foreach ($type as $value) {
                try {
                    return $this->getRealObjectType($value);
                } catch (WrongWayException) {}
            }
            throw new WrongWayException();
        }

        if (!class_exists($type)) throw new WrongWayException();

        return $type;
    }

    /**
     * @throws RpcInternalException
     */
    protected function detectDtoOnType(string $type, array $refCollection = []): ?array
    {
        $jsonValue = null;
        if ($type === 'collection' && !empty($refCollection)) {
            $jsonValue = [
                T::TYPE => T::ARRAY->value,
                T::ITEMS => $refCollection['schema'] ?? []
            ];
            $dto = $refCollection['format'] ?? null;
            if ($dto instanceof DTO && !isset($this->schemas[$dto?->getFormat()['$dto']])) {
                $this->schemaFromDto($dto->getFormat());
            }
        }
        if (!$jsonValue && EnumResolver::getEnumFQCN($type)) {
            $jsonValue = EnumResolver::generateEnumSchema($type);
        } elseif (!$jsonValue && T::isRealClass($type)) {
            $newDtoResponse = new DTO($type);
            new DtoReflector($newDtoResponse, $this->getParamConvertor());
            if (isset($this->schemas[$newDtoResponse->getFormat()['$dto']])) {
                return $this->createSchemaLink($newDtoResponse->getFormat()['$dto']);
            }
            $jsonValue = $this->schemaFromDto($newDtoResponse->getFormat());
        }
        return $jsonValue;
    }

    /**
     * @throws RpcInternalException
     */
    protected function detectArrayOfType(string $type, array $uses = []): array
    {
        return T::typeDescriptionToJsonSchema($type, $uses);
    }

    protected function createSchemaLink(string $dtoName): array
    {
        return [T::REF => static::SCHEMAS . $dtoName];
    }

    protected function formatFromResponse(Service $service): ?array
    {
        $res = $this->detectArrayOfType(implode('|', $service->getReturn()));
        return [T::TYPE => ($res[T::TYPE] ?? $res)];
    }

    /**
     * @throws RpcInternalException
     */
    protected function formatFromResultAsDto(DTO $responseInfo): ?array
    {
        $schema = [];
        try {
            $format = $responseInfo->getFormat();
        } catch (RpcInternalException) {
            new DtoReflector($responseInfo, $this->getParamConvertor());
            $format = $responseInfo->getFormat();
        }
        if ($responseInfo->isCollection()) {
            $schema[T::TYPE] = T::ARRAY->value;
            $schema[T::ITEMS] = $this->schemaFromDto($format);
        } else {
            $schema = $this->schemaFromDto($format);
        }
        return $schema;
    }


    /**
     * @throws RpcInternalException
     */
    protected function rpcResponseInfoToSchema(?DTO $responseInfo): ?array
    {
        if (is_null($responseInfo)) return null;
        return $this->formatFromResultAsDto($responseInfo);
    }

    protected function applyEnumRefToSchema(array $paramSchema, string $enumName, array $enumData): array
    {
        return T::applyToSchema(
            $paramSchema,
            function (array $schema) use ($enumName, $enumData): array
            {
                if (($schema[T::TYPE] ?? '') === ($enumData[T::TYPE] ?? null)) {
                    $schema = [T::REF => static::SCHEMAS . $enumName];
                }
                return $schema;
            }
        );
    }
}