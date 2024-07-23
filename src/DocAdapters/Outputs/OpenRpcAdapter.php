<?php
namespace Ufo\JsonRpcBundle\DocAdapters\Outputs;

use PSX\OpenRPC\Method;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\OpenRpc\OpenRpcSpecBuilder;
use Ufo\JsonRpcBundle\Package;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ResultAsDtoReflector;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcObject\Helpers\TypeHintResolver;
use Ufo\RpcObject\RPC\Response;
use Ufo\RpcObject\RPC\ResultAsDTO;
use Ufo\RpcObject\RpcTransport;

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
            rpcEnv: Package::ufoEnvironment(),
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
            fn($param) => $this->buildParam($method, $param, $service),
            $service->getParams()
        );

        $schema = $this->rpcResponseInfoToSchema($service->getResponseInfo())
            ?? ['type' => implode(', ', $service->getReturn())];

        $this->rpcSpecBuilder->buildResult(
            $method,
            implode(', ', $service->getReturn()),
            $service->getReturnDescription(),
            $schema
        );
        $this->rpcSpecBuilder->buildTag($method, $service->getProcedureFQCN());
//        $this->rpcSpecBuilder->buildError($method);
    }

    protected function rpcResponseInfoToSchema(null|Response|ResultAsDTO $responseInfo): ?array
    {
        if (is_null($responseInfo)) return null;

        if ($responseInfo instanceof ResultAsDTO) {
            $schema = $this->formatFromResultAsDto($responseInfo);
        } else {
            $schema = $this->formatFromResponse($responseInfo);
        }
        return $schema;
    }

    protected function formatFromResultAsDto(ResultAsDTO $responseInfo): ?array
    {
        $schema = [];
        try {
            $format = $responseInfo->getResponseFormat();
        } catch (RpcInternalException) {
            new ResultAsDtoReflector($responseInfo);
            $format = $responseInfo->getResponseFormat();
        }
        if ($responseInfo->collection) {
            $schema['type'] = 'array';
            $schema['items'] = $this->schemaFromDto($format);
        } else {
            $schema = $this->schemaFromDto($format);
        }
        return $schema;
    }

    protected function formatFromResponse(Response $responseInfo): ?array
    {
        return $this->formatFromResultAsDto(
            new ResultAsDTO(
                $responseInfo->getDto(),
                $responseInfo->isCollection()
            )
        );
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
        $collections = array_map(
            function (ResultAsDTO $res) {
                return [
                    'schema' => $this->createSchemaLink($res->getResponseFormat()['$dto']),
                    'format' => $res
                ];
            } , $format['$collections'] ?? []
        );
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
        if ($type === 'collection') {
            $jsonValue = [
                'type' => 'array',
                'items' => $refCollection['schema']
            ];
            if (!isset($this->schemas[$refCollection['format']->getResponseFormat()['$dto']])) {
                $this->schemaFromDto($refCollection['format']->getResponseFormat());
            }
        }
        if (!$jsonValue && TypeHintResolver::isRealClass($type)) {
            $newDtoResponse = new ResultAsDTO($type);
            new ResultAsDtoReflector($newDtoResponse);
            if (isset($this->schemas[$newDtoResponse->getResponseFormat()['$dto']])) {
                return $this->createSchemaLink($newDtoResponse->getResponseFormat()['$dto']);
            }
            $jsonValue = $this->schemaFromDto($newDtoResponse->getResponseFormat());
        }
        return $jsonValue;
    }

    protected function buildParam(Method $method, array $param, Service $service): void
    {
        $schema = $service->getSchema()['properties'] ?? [];

        $assertions = $service->getAssertions()?->getAssertionsCollection()[$param['name']]?->constructorArgs ?? null;

        $this->rpcSpecBuilder->buildParam(
            $method,
            $param['name'],
            $param['description'],
            !$param['optional'],
            $param['default'] ?? null,
            $schema[$param['name']] ?? [],
            $assertions
        );
    }
}


