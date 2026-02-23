<?php
namespace Ufo\JsonRpcBundle\DocAdapters\Outputs;

use Symfony\Component\Routing\RouterInterface;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Method;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\PostmanSpecBuilder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RpcTransport;

use function explode;

class PostmanAdapter
{
    protected PostmanSpecBuilder $postmanSpecBuilder;
    protected string $version = Info::DEFAULT_VERSION;

    public function __construct(
        protected ServiceMap $serviceMap,
        protected RpcMainConfig $mainConfig,
        protected RouterInterface $router
    ) {}

    public function adapt(string $version = Info::DEFAULT_VERSION): array
    {
        $this->version = $version;
        $this->buildSignature();
        $this->buildServer();
        $this->buildServices();
        return $this->postmanSpecBuilder->build();
    }

    protected function buildSignature(): void
    {
        $this->postmanSpecBuilder = PostmanSpecBuilder::createBuilder(
            name: 'RPC: ' . $this->mainConfig->docsConfig->projectName,
            description: $this->mainConfig->docsConfig->projectDesc,
            version: $this->version,
        );
    }

    protected function buildServer(): void
    {
        $http = RpcTransport::fromArray($this->mainConfig->url);
        $path = $this->router->generate(ApiController::API_ROUTE);
        if ($this->version !== Info::DEFAULT_VERSION) {
            $path = $this->router->generate(ApiController::API_ROUTE_VER, ['ver' => $this->version]);
        }
        $this->postmanSpecBuilder->addVariable('base_url', $http->getDomainUrl());

        $this->postmanSpecBuilder->addServer(
            $http->getDomainUrl() . $path,
        );
    }

    protected function buildServices(): void
    {
        foreach ($this->serviceMap->getServices($this->version) as $service) {
            $this->buildService($service);
        }
    }

    protected function buildService(Service $service): void
    {
        $variableApiToken = $this->postmanSpecBuilder->addVariable('apiToken', '!changeMe!');
        $headers = [
            [
                'key' => 'Content-Type',
                'value' => 'application/json',
            ],
            [
                'key' => $this->mainConfig->securityConfig->tokenName,
                'value' => '{{' . $variableApiToken->key . '}}',
            ]
        ];

        $method = $this->postmanSpecBuilder->buildMethod(
            $service->getName(),
            $service->getDescription(),
            $headers,
            $service->procedure
        );

        foreach ($service->getParams() as $param) {
            $this->buildParam($method, $param);
        }
    }

    protected function buildParam(Method $method, ParamDefinition $param): void
    {
        $this->postmanSpecBuilder->buildParam(
            $method,
            $param
        );
    }

}
