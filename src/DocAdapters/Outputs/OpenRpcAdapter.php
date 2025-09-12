<?php
namespace Ufo\JsonRpcBundle\DocAdapters\Outputs;

use PSX\OpenRPC\Method;
use Ufo\DTO\Helpers\EnumsHelper;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\OpenRpc\OpenRpcSpecBuilder;
use Ufo\JsonRpcBundle\Package;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\DtoReflector;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;
use Ufo\RpcError\RpcInternalException;
use Ufo\DTO\Helpers\TypeHintResolver as T;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\DTO;
use Ufo\RpcObject\RPC\ResultAsDTO;
use Ufo\RpcObject\RpcTransport;

use function array_column;
use function array_map;
use function class_exists;
use function explode;
use function implode;
use function is_array;
use function is_null;
use function is_string;
use function str_contains;
use function str_starts_with;
use function substr;

class OpenRpcAdapter
{

    protected OpenRpcSpecBuilder $rpcSpecBuilder;

    protected array $schemas = [];

    public function __construct(
        protected ServiceMap $serviceMap,
        protected RpcMainConfig $mainConfig,
        protected ChainParamConvertor $paramConvertor,
    ) {}

    public function adapt(): array
    {
        $this->buildSignature();
        $this->buildServer();
        $this->buildServices();
        $this->buildComponents();
        return $this->rpcSpecBuilder->build();
    }

    protected function buildSignature(): void
    {
        $this->rpcSpecBuilder = OpenRpcSpecBuilder::createBuilder(
            $this->mainConfig->docsConfig->projectName,
            $this->serviceMap->getDescription(),
            $this->mainConfig->docsConfig->projectVersion ?? 'latest',
            licenseName: Package::projectLicense(),
            contactName: Package::bundleName(),
            contactLink: Package::bundleDocumentation(),
        );
    }

    protected function buildComponents(): void
    {
        if (empty($this->schemas)) return;
        $this->rpcSpecBuilder->setComponents($this->schemas);
    }

    protected function buildServer(): void
    {
        $http = RpcTransport::fromArray($this->mainConfig->url);
        $this->rpcSpecBuilder->addServer(
            $http->getDomainUrl().$this->serviceMap->getTarget(),
            $this->serviceMap->getEnvelope(),
            $this->serviceMap->getTransport(),
            rpcEnv: [
                ...['fromCache' => $this->serviceMap->isFromCache()],
                ...Package::ufoEnvironment(),
            ],
        );
    }

    protected function buildServices(): void
    {
        foreach ($this->serviceMap->getServices() as $service) {
            $this->buildService($service);
        }
    }

    protected function buildService(Service $service): void
    {
        $method = $this->rpcSpecBuilder->buildMethod(
            $service->getName(),
            $service->getDescription()
        );
        array_map(
            fn(ParamDefinition $param) => $this->buildParam($method, $param, $service),
            $service->getParams()
        );
        $objSchema = null;
        if ($items = $service->getReturnItems() ?? implode('|', $service->getReturn())) {
            $objSchema = $this->toJsonSchema($items, $service);
            if ($objSchema[T::ITEMS] ?? false) {
                $items = &$objSchema[T::ITEMS];
                $items = $this->checkAndGetSchemaFromDesc($items);
            }
        }

        $schema = $this->rpcResponseInfoToSchema($service->getResponseInfo()) ?? $this->formatFromResponse($service);

        if ($objSchema) {
            $schema = [
                ...$schema,
                ...$objSchema
            ];
        }
        $this->rpcSpecBuilder->buildResult(
            $method,
            implode(', ', $service->getReturn()),
            $service->getReturnDescription(),
            $schema
        );
        $this->rpcSpecBuilder->buildTag($method, $service->getProcedureFQCN());
        //        $this->rpcSpecBuilder->buildError($method);
    }



    /**
     * @throws RpcInternalException
     */
    protected function rpcResponseInfoToSchema(?DTO $responseInfo): ?array
    {
        if (is_null($responseInfo)) return null;

        return $this->formatFromResultAsDto($responseInfo);
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
            new DtoReflector($responseInfo, $this->paramConvertor);
            $format = $responseInfo->getFormat();
        }
        if ($responseInfo->collection) {
            $schema[T::TYPE] = T::ARRAY->value;
            $schema[T::ITEMS] = $this->schemaFromDto($format);
        } else {
            $schema = $this->schemaFromDto($format);
        }
        return $schema;
    }

    protected function formatFromResponse(Service $service): ?array
    {
        $res = $this->detectArrayOfType(implode('|', $service->getReturn()));
        return [T::TYPE => ($res[T::TYPE] ?? $res)];
    }

    protected function createSchemaLink(string $dtoName): array
    {
        return ['$ref' => '#/components/schemas/' . $dtoName];
    }

    /**
     * @throws RpcInternalException
     */
    protected function schemaFromDto(array $format): array
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

        $schemaLink = $this->createSchemaLink($dtoName);
        if (!isset($this->schemas[$dtoName])) {
            $this->schemas[$dtoName] = [];
            $schema = [
                T::TYPE => T::OBJECT->value,
                'properties' => [],
                'required' => []
            ];
            foreach ($format as $name => $value) {
                if (str_starts_with($value, '?')) {
                    $value = substr($value, 1) . '|null';
                } else {
                    $schema['required'][] = $name;
                }

                if (!$jsonValue = $this->detectDtoOnType($value, $collections[$name] ?? [])) {
                    $jsonValue = $this->detectArrayOfType($value, $uses);
                }
                $this->replaceClassNameToDTO(
                    $jsonValue,
                    $uses,
                );

                $schema['properties'][$name] = $jsonValue;
            }
            $this->schemas[$dtoName] = $schema;
        }
        return $schemaLink;
    }

    /**
     * @throws RpcInternalException
     */
    protected function detectArrayOfType(string $type, array $uses = []): array
    {
        return T::typeDescriptionToJsonSchema($type, $uses);
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
        if (!$jsonValue && $this->getEnumFQCN($type)) {
            $jsonValue = EnumsHelper::generateEnumSchema($type);
        } elseif (!$jsonValue && T::isRealClass($type)) {
            $newDtoResponse = new DTO($type);
            new DtoReflector($newDtoResponse, $this->paramConvertor);
            if (isset($this->schemas[$newDtoResponse->getFormat()['$dto']])) {
                return $this->createSchemaLink($newDtoResponse->getFormat()['$dto']);
            }
            $jsonValue = $this->schemaFromDto($newDtoResponse->getFormat());
        }
        return $jsonValue;
    }

    protected function checkParamHasDTO(string $type, string|array $realType, array $uses = [], ?DTO $dtoAttr = null): ?DTO
    {
        $dto = null;
        $class = null;
        if ($type) {
            $objSchema = T::typeDescriptionToJsonSchema($type, $uses);
            if ($objSchema[T::TYPE] ?? '' === T::OBJECT->value) {
                $class = $objSchema['classFQCN'] ?? null;
            }
        }

        try {
            $class ??= $this->getRealObjectType($realType);
            $dto = $this->createDTO($class, $dtoAttr);
        } catch (WrongWayException) {}

        return $dto;
    }

    protected function replaceClassNameToDTO(array &$objSchema, array $uses = []): void
    {
        $type = $objSchema[T::TYPE] ?? '';
        if ($type === T::OBJECT->value && $class = ($objSchema['classFQCN'] ?? false)) {
            $dto = $this->createDTO($class, null);
            $objSchema = $this->schemaFromDto($dto->getFormat());


        } elseif ($type === T::ARRAY->value && ($objSchema[T::ITEMS] ?? false)) {
            $this->replaceClassNameToDTO($objSchema[T::ITEMS], $uses);
        } elseif ($objSchema[T::ONE_OFF] ?? false) {
            foreach ($objSchema[T::ONE_OFF] as &$objSchema_) {
                $this->replaceClassNameToDTO($objSchema_);
            }
        }
    }

    protected function createDTO(string $classFQCN, ?DTO $dtoAttr): DTO
    {
        $dto = $dtoAttr ?? new DTO($classFQCN);
        new DtoReflector($dto, $this->paramConvertor);
        return $dto;
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

    // todo refactoring update schema for enum
    protected function buildParam(Method $method, ParamDefinition $param, Service $service): void
    {
        $schema = $service->getSchema()['properties'] ?? [];

        if ($enumFQCN = $this->getEnumFQCN($param->getRealType())) {
            $schema[$param->name] = EnumsHelper::generateEnumSchema($enumFQCN);
        } elseif ($param->getType() === T::OBJECT->value
            && $dto = $this->checkParamHasDTO(
                $param->paramItems ?? '',
                $param->getRealType(),
                $service->uses,
                $param->getAttributesCollection()->getAttribute(DTO::class)
            )
        ) {
            $schema[$param->name] = $this->schemaFromDto($dto->getFormat());
        } elseif ($param->getType() === T::ARRAY->value) {
            $newSchema = T::typeDescriptionToJsonSchema($param->paramItems ?? $param->getType(), $service->uses);


            if ($newSchema[T::ONE_OFF] ?? false) {
                $types = &$newSchema[T::ONE_OFF];
                foreach ($types as $i => $objSchema) {
                    $types[$i] = $this->checkAndGetSchemaFromDesc($objSchema, $param->getAttributesCollection()->getAttribute(DTO::class));
                }
            }
            
            if ($schema[$param->name][T::ITEMS][EnumsHelper::ENUM_KEY] ?? false) {
                $newSchema[T::ITEMS][EnumsHelper::ENUM_KEY] = $schema[$param->name][T::ITEMS][EnumsHelper::ENUM_KEY];
            }

            if (($newSchema[T::ITEMS][T::ONE_OFF] ?? false) && ($newSchema[T::ITEMS][EnumsHelper::ENUM_KEY] ?? false)) {
                $type = gettype($newSchema[T::ITEMS][EnumsHelper::ENUM_KEY][0]);


                foreach ($newSchema[T::ITEMS][T::ONE_OFF] as $i => $objSchema) {
                    if ($objSchema[T::TYPE] === $type) {
                        $newSchema[T::ITEMS][T::ONE_OFF][$i][EnumsHelper::ENUM_KEY] = $newSchema[T::ITEMS][EnumsHelper::ENUM_KEY];
                        unset($newSchema[T::ITEMS][EnumsHelper::ENUM_KEY]);
                        break;
                    }
                }
            }
            
            if (($newSchema[T::ITEMS] ?? false) && ($newSchema[T::ONE_OFF] ?? false)) {
                unset($schema[$param->name][T::ITEMS]);
                unset($newSchema[T::ITEMS]);
            }

            $schema[$param->name] = [
                ...$schema[$param->name],
                ...$newSchema,
            ];

            if ($classFQCN = $newSchema[T::ITEMS]['classFQCN']  ?? false) {
                $dto = $this->checkParamHasDTO(
                    $param->paramItems ?? '',
                    $classFQCN,
                    $service->uses,
                    $param->getAttributesCollection()->getAttribute(DTO::class)
                );
                $schema[$param->name][T::ITEMS] = $this->schemaFromDto($dto->getFormat());
            }

        } elseif (is_array($param->getType())) {
            if ($param->paramItems) {
                $newSchema = T::typeDescriptionToJsonSchema($param->paramItems, $service->uses);
                $schema[$param->name] = $this->checkAndGetSchemaFromDesc($newSchema, null);
            } else {
                foreach ($param->getType() as $i => $type) {
                    if ($type === T::OBJECT->value && ($param->getRealType()[$i] ?? false)) {
                        $type = $param->getRealType()[$i];
                    }
                    $newSchema = T::typeDescriptionToJsonSchema($type, $service->uses);
                    $schema[$param->name][T::ONE_OFF][$i] = $this->checkAndGetSchemaFromDesc($newSchema, null);
                }
            }
        }

        $this->rpcSpecBuilder->buildParam(
            $method,
            $param->name,
            $param->description,
            !$param->isOptional(),
            $param->getDefault(),
            $schema[$param->name] ?? [],
            $service->getUfoAssertion($param->name)
        );
    }

    protected function getEnumFQCN(string|array $type): ?string
    {
        if (is_array($type)) {
            foreach ($type as $value) {
                if ($res = $this->getEnumFQCN($value)) {
                    return $res;
                }
            }
        }

        return (is_string($type) && T::isEnum($type)) ? $type : null;
    }

    protected function checkAndGetSchemaFromDesc(array $objSchema, ?DTO $dtoAttr = null): array
    {
        if (($objSchema[T::TYPE] ?? '') === T::OBJECT->value
            && ($objSchema['classFQCN'] ?? false)) {
            $dto = $this->createDTO($objSchema['classFQCN'], $dtoAttr);
            return $this->schemaFromDto($dto->getFormat());
        }
        return $objSchema;
    }

    protected function toJsonSchema(string $data, Service $service): ?array
    {
        $objSchema = T::typeDescriptionToJsonSchema($data, $service->uses);
        if ($objSchema['classFQCN'] ?? false) {
            $service->setResponseInfo(
                new ResultAsDTO(
                    $objSchema['classFQCN'],
                    $objSchema[T::TYPE] === T::ARRAY->value
                )
            );
            new DtoReflector($service->getResponseInfo(), $this->paramConvertor);
            unset($objSchema[T::TYPE]);
            unset($objSchema['classFQCN']);
            unset($objSchema['additionalProperties']);
        }
        return $objSchema;
    }
}