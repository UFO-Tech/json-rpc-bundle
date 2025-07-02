<?php
namespace Ufo\JsonRpcBundle\DocAdapters\Outputs;

use PSX\OpenRPC\Method;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\OpenRpc\OpenRpcSpecBuilder;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\OpenRpc\UfoRpcParameter;
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
use Ufo\RpcObject\RpcTransport;
use Ufo\RpcObject\DocsHelper;
use Ufo\RpcObject\DocsHelper\UfoEnumsHelper;

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
        protected ChainParamConvertor $paramConvertor
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
            $http->getDomainUrl() . $this->serviceMap->getTarget(),
            $this->serviceMap->getEnvelope(),
            $this->serviceMap->getTransport(),
            rpcEnv: [
                ...['fromCache' => $this->serviceMap->isFromCache()],
                ...Package::ufoEnvironment(),
            ],
            relations: $this->mainConfig->sdkVendors ?? []
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

        $schema = $this->rpcResponseInfoToSchema($service->getResponseInfo()) ?? $this->formatFromResponse($service);

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
            $schema['type'] = 'array';
            $schema['items'] = $this->schemaFromDto($format);
        } else {
            $schema = $this->schemaFromDto($format);
        }
        return $schema;
    }

    protected function formatFromResponse(Service $service): ?array
    {
        $res = $this->detectArrayOfType(implode('|', $service->getReturn()));
        return ['type' => ($res['type'] ?? $res)];
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
        unset($format['$dto']);
        unset($format['$collections']);

        $schemaLink = $this->createSchemaLink($dtoName);
        if (!isset($this->schemas[$dtoName])) {
            $this->schemas[$dtoName] = [];
            $schema = [
                'type' => 'object',
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
                    $jsonValue = $this->detectArrayOfType($value);
                }

                $schema['properties'][$name] = $jsonValue;
            }
            $this->schemas[$dtoName] = $schema;
        }
        return $schemaLink;
    }

    protected function schemaFromEnum(string $enumFQCN): array
    {
        $refEnum = new \ReflectionEnum($enumFQCN);
        $enum = $refEnum->getBackingType();
        return [
            'enum' => array_column($enumFQCN::cases(), 'value'),
            'type' => TypeHintResolver::phpToJsonSchema($refEnum->getBackingType()->getName()),
            ...UfoEnumsHelper::generateEnumSchema($enumFQCN),
        ];
    }

    /**
     * @throws RpcInternalException
     */
    protected function detectArrayOfType(string $type): array
    {
        $jsonValue = [TypeHintResolver::TYPE => TypeHintResolver::phpToJsonSchema($type)];
        if (str_contains($type, '|')) {
            $phpTypes = explode('|', $type);

            $types = [];
            foreach ($phpTypes as $type) {
                if (!$t = $this->detectDtoOnType($type)) {
                    $t = [TypeHintResolver::TYPE => TypeHintResolver::phpToJsonSchema($type)];
                }
                $types[] = $t;
            }
            $jsonValue = ['oneOf' => $types];
        }
        return $jsonValue;

    }

    /**
     * @throws RpcInternalException
     */
    protected function detectDtoOnType(string $type, array $refCollection = []): ?array
    {
        $jsonValue = null;
        if ($type === 'collection' && !empty($refCollection)) {
            $jsonValue = [
                'type' => 'array',
                'items' => $refCollection['schema'] ?? []
            ];
            $dto = $refCollection['format'] ?? null;
            if ($dto instanceof DTO && !isset($this->schemas[$dto?->getFormat()['$dto']])) {
                $this->schemaFromDto($dto->getFormat());
            }
        }
        if (!$jsonValue && TypeHintResolver::isEnum($type)) {
            $jsonValue = $this->schemaFromEnum($type);
        } elseif (!$jsonValue && TypeHintResolver::isRealClass($type)) {
            $newDtoResponse = new DTO($type);
            new DtoReflector($newDtoResponse, $this->paramConvertor);
            if (isset($this->schemas[$newDtoResponse->getFormat()['$dto']])) {
                return $this->createSchemaLink($newDtoResponse->getFormat()['$dto']);
            }
            $jsonValue = $this->schemaFromDto($newDtoResponse->getFormat());
        }
        return $jsonValue;
    }

    protected function checkParamHasDTO(ParamDefinition $param): ?DTO
    {
        $dto = null;

        try {
            $class = $this->getRealObjectType($param->getRealType());

            $dtoAttr = $param->getAttributesCollection()->getAttribute(DTO::class);
            $dto = $dtoAttr ?? new DTO($class);
            new DtoReflector($dto, $this->paramConvertor);
        } catch (WrongWayException) {}

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
            && TypeHintResolver::isEnum($param->getRealType())
        ) {
            $schema[$param->name] = [
                ...$schema[$param->name] ?? [],
                ...$jsonValue = $this->schemaFromEnum($param->getRealType())
            ];

        } elseif ($param->getType() === TypeHintResolver::OBJECT->value
            && $dto = $this->checkParamHasDTO($param)
        ) {
            $schema[$param->name] = [
                ...$schema[$param->name] ?? [],
                ...$this->schemaFromDto($dto->getFormat())
            ];
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
}


