<?php
namespace Ufo\JsonRpcBundle\DocAdapters\Outputs;

use PSX\OpenRPC\Method;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\OpenRpc\OpenRpcSpecBuilder;
use Ufo\JsonRpcBundle\Package;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\DtoReflector;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;
use Ufo\RpcError\RpcInternalException;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\DTO;
use Ufo\RpcObject\RPC\ResultAsDTO;
use Ufo\RpcObject\RpcTransport;

use function array_map;
use function explode;
use function implode;
use function is_null;
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
            if ($objSchema[TypeHintResolver::ITEMS] ?? false) {
                $items = &$objSchema[TypeHintResolver::ITEMS];
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
            $schema[TypeHintResolver::TYPE] = TypeHintResolver::ARRAY->value;
            $schema[TypeHintResolver::ITEMS] = $this->schemaFromDto($format);
        } else {
            $schema = $this->schemaFromDto($format);
        }
        return $schema;
    }

    protected function formatFromResponse(Service $service): ?array
    {
        $res = $this->detectArrayOfType(implode('|', $service->getReturn()));
        return [TypeHintResolver::TYPE => ($res[TypeHintResolver::TYPE] ?? $res)];
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
                TypeHintResolver::TYPE => TypeHintResolver::OBJECT->value,
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
        return TypeHintResolver::typeDescriptionToJsonSchema($type, $uses);
    }

    /**
     * @throws RpcInternalException
     */
    protected function detectDtoOnType(string $type, array $refCollection = []): ?array
    {
        $jsonValue = null;
        if ($type === 'collection' && !empty($refCollection)) {
            $jsonValue = [
                TypeHintResolver::TYPE => TypeHintResolver::ARRAY->value,
                TypeHintResolver::ITEMS => $refCollection['schema'] ?? []
            ];
            $dto = $refCollection['format'] ?? null;
            if ($dto instanceof DTO && !isset($this->schemas[$dto?->getFormat()['$dto']])) {
                $this->schemaFromDto($dto->getFormat());
            }
        }
        if (!$jsonValue && TypeHintResolver::isRealClass($type)) {
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
            $objSchema = TypeHintResolver::typeDescriptionToJsonSchema($type, $uses);
            if ($objSchema[TypeHintResolver::TYPE] ?? '' === TypeHintResolver::OBJECT->value) {
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
        $type = $objSchema[TypeHintResolver::TYPE] ?? '';
        if ($type === TypeHintResolver::OBJECT->value && $class = ($objSchema['classFQCN'] ?? false)) {
            $dto = $this->createDTO($class, null);
            $objSchema = $this->schemaFromDto($dto->getFormat());


        } elseif ($type === TypeHintResolver::ARRAY->value && ($objSchema[TypeHintResolver::ITEMS] ?? false)) {
            $this->replaceClassNameToDTO($objSchema[TypeHintResolver::ITEMS], $uses);
        } elseif ($objSchema[TypeHintResolver::ONE_OFF] ?? false) {
            foreach ($objSchema[TypeHintResolver::ONE_OFF] as &$objSchema_) {
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

    protected function buildParam(Method $method, ParamDefinition $param, Service $service): void
    {
        $schema = $service->getSchema()['properties'] ?? [];

        if ($param->getType() === TypeHintResolver::OBJECT->value 
            && $dto = $this->checkParamHasDTO(
                $param->paramItems, 
                $param->getRealType(),
                $service->uses,
                $param->getAttributesCollection()->getAttribute(DTO::class)
            )
        ) {
            $schema[$param->name] = $this->schemaFromDto($dto->getFormat());
        } elseif ($param->getType() === TypeHintResolver::ARRAY->value) {
            $newSchema = TypeHintResolver::typeDescriptionToJsonSchema($param->paramItems ?? $param->getType(), $service->uses);
            if ($newSchema[TypeHintResolver::ONE_OFF] ?? false) {
                $types = &$newSchema[TypeHintResolver::ONE_OFF];
                foreach ($types as $i => $objSchema) {
                    $types[$i] = $this->checkAndGetSchemaFromDesc($objSchema, $param->getAttributesCollection()->getAttribute(DTO::class));
                }
            }
            
            $schema[$param->name] = $newSchema;
        } elseif (is_array($param->getType())) {
            if ($param->paramItems) {
                $newSchema = TypeHintResolver::typeDescriptionToJsonSchema($param->paramItems, $service->uses);
                $schema[$param->name] = $this->checkAndGetSchemaFromDesc($newSchema, null);
            } else {
                foreach ($param->getType() as $i => $type) {
                    $newSchema = TypeHintResolver::typeDescriptionToJsonSchema($type, $service->uses);
                    $schema[$param->name][TypeHintResolver::ONE_OFF][$i] = $this->checkAndGetSchemaFromDesc($newSchema, null);
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

    protected function checkAndGetSchemaFromDesc(array $objSchema, ?DTO $dtoAttr = null): array
    {
        if (($objSchema[TypeHintResolver::TYPE] ?? '') === TypeHintResolver::OBJECT->value) {
            $class = $objSchema['classFQCN'] ?? null;
            $dto = $this->createDTO($class, $dtoAttr);
            return $this->schemaFromDto($dto->getFormat());
        }
        return $objSchema;
    }

    protected function toJsonSchema(string $data, Service $service): ?array
    {
        $objSchema = TypeHintResolver::typeDescriptionToJsonSchema($data, $service->uses);
        if ($objSchema['classFQCN'] ?? false) {
            $service->setResponseInfo(
                new ResultAsDTO(
                    $objSchema['classFQCN'],
                    $objSchema[TypeHintResolver::TYPE] === TypeHintResolver::ARRAY->value
                )
            );
            new DtoReflector($service->getResponseInfo(), $this->paramConvertor);
            unset($objSchema[TypeHintResolver::TYPE]);
            unset($objSchema['classFQCN']);
            unset($objSchema['additionalProperties']);
        }
        return $objSchema;
    }
}


