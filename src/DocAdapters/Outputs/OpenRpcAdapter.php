<?php
namespace Ufo\JsonRpcBundle\DocAdapters\Outputs;

use PSX\OpenRPC\Method;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\OpenRpc\OpenRpcSpecBuilder;
use Ufo\JsonRpcBundle\Package;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;
use Ufo\RpcObject\Helpers\TypeHintResolver;
use Ufo\RpcObject\RPC\Response;
use Ufo\RpcObject\RpcTransport;

use function array_map;
use function implode;
use function is_array;
use function is_null;
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
            $this->serviceMap->getTransport()
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

    protected function rpcResponseInfoToSchema(?Response $responseInfo): ?array
    {
        if (is_null($responseInfo) || is_null($responseInfo->getResponseFormat())) return null;

        $schema = [];
        $format = $responseInfo->getResponseFormat();
        if ($responseInfo->isCollection()) {
            $format = $format[0];
            $schema['type'] = 'array';
            $schema['items'] = $this->schemaFromDto($format);
        } else {
            $schema = $this->schemaFromDto($format);
        }
        return $schema;
    }

    protected function schemaFromDto(array $format): array
    {
        $dtoName = $format['$dto'];
        unset($format['$dto']);

        $schemaLink = ['$ref' => '#/components/schemas/' . $dtoName];
        if (!isset($this->schemas[$dtoName])) {
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
                $jsonValue = TypeHintResolver::phpToJsonSchema($value);

                    $jsonValue = [
                        ((is_array($jsonValue))? 'oneOf' : 'type') => $jsonValue
                    ];

                $schema['properties'][$name] = $jsonValue;
            }
            $this->schemas[$dtoName] = $schema;
        }
        return $schemaLink;
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


