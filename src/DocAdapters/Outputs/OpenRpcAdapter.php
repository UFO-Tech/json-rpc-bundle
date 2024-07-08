<?php
namespace Ufo\JsonRpcBundle\DocAdapters\Outputs;

use PSX\OpenRPC\Method;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\OpenRpc\OpenRpcSpecBuilder;
use Ufo\JsonRpcBundle\Package;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceLocator;
use Ufo\RpcObject\RpcTransport;

use function array_map;
use function implode;

class OpenRpcAdapter
{

    protected OpenRpcSpecBuilder $rpcSpecBuilder;

    public function __construct(
        protected ServiceLocator $serviceLocator,
    ) {}

    public function adapt(): array
    {
        $this->buildSignature();
        $this->buildServer();
        $this->buildServices();
        return $this->rpcSpecBuilder->build();
    }

    protected function buildSignature(): void
    {
        $this->rpcSpecBuilder = OpenRpcSpecBuilder::createBuilder(
            $this->serviceLocator->getMainConfig()->projectName,
            $this->serviceLocator->getDescription(),
            $this->serviceLocator->getMainConfig()->projectVersion,
            licenseName: Package::projectLicense(),
            contactName: Package::bundleName(),
            contactLink: Package::bundleDocumentation(),
        );
    }

    protected function buildServer(): void
    {
        $http = RpcTransport::fromArray($this->serviceLocator->getMainConfig()->url);
        $this->rpcSpecBuilder->addServer(
            $http->getDomainUrl() . $this->serviceLocator->getTarget(),
            $this->serviceLocator->getEnvelope(),
        );
    }

    protected function buildServices(): void
    {
        foreach ($this->serviceLocator->getServices() as $service) {
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
            fn($param) => $this->buildParam($method, $param, $service->getSchema()['properties'] ?? []),
            $service->getParams()
        );

        $this->rpcSpecBuilder->buildResult(
            $method,
            implode(', ', $service->getReturn()),
            $service->getReturnDescription(),
            ['type' => implode(', ', $service->getReturn())]
        );
        $this->rpcSpecBuilder->buildTag($method, $service->getProcedure()::class);
//        $this->rpcSpecBuilder->buildError($method);
    }

    protected function buildParam(Method $method, array $param, array $schema): void
    {
        $this->rpcSpecBuilder->buildParam(
            $method,
            $param['name'],
            $param['description'],
            !$param['optional'],
            $param['default'] ?? null,
            $schema[$param['name']] ?? [],
        );
    }
}


