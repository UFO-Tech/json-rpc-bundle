<?php
namespace Ufo\JsonRpcBundle\DocAdapters\Outputs;

use Symfony\Component\Routing\RouterInterface;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Method;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\PostmanSpecBuilder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RpcTransport;

abstract class AbstractPostmanAdapter
{
    protected PostmanSpecBuilder $postmanSpecBuilder;
    protected string $version = Info::DEFAULT_VERSION;

    protected const string PREFIX = '';
    protected const string ROUTE = '';
    protected const string ROUTE_VER = '';

    public function __construct(
        protected RpcMainConfig $mainConfig,
        protected RouterInterface $router,
        protected ApiGroupContext $apiGroupContext,
    ) {}

    public function adapt(string $version = Info::DEFAULT_VERSION): array
    {
        $this->version = $version;
        $this->buildSignature();
        $this->buildServer();
        $this->buildServices();
        return $this->postmanSpecBuilder->build();
    }

    abstract protected function createSpecBuilder(string $name, string $description, string $version): PostmanSpecBuilder;

    abstract protected function buildServices(): void;

    protected function buildSignature(): void
    {
        $this->postmanSpecBuilder = $this->createSpecBuilder(
            name: $this->buildName(),
            description: $this->mainConfig->docsConfig->projectDesc,
            version: $this->version,
        );
    }

    protected function buildName(): string
    {
        $name = static::PREFIX . ': ' . $this->mainConfig->docsConfig->projectName;
        if (!$this->apiGroupContext->isDefault()) {
            $name .= ' [' . $this->apiGroupContext->getGroup() . ']';
        }
        return $name;
    }

    protected function getTransport(): RpcTransport
    {
        return RpcTransport::fromArray($this->mainConfig->url);
    }

    protected function buildServer(): void
    {
        $http = $this->getTransport();
        $path = $this->router->generate($this->apiGroupContext->getRouteName(static::ROUTE));
        if ($this->version !== Info::DEFAULT_VERSION) {
            $path = $this->router->generate($this->apiGroupContext->getRouteName(static::ROUTE_VER), ['ver' => $this->version]);
        }
        $this->postmanSpecBuilder->addVariable('base_url', $http->getDomainUrl());

        $this->postmanSpecBuilder->addServer(
            $http->getDomainUrl() . $path,
        );
    }

    protected function buildHeaders(): array
    {
        $variableApiToken = $this->postmanSpecBuilder->addVariable('apiToken', '!changeMe!');
        return [
            [
                'key' => 'Content-Type',
                'value' => 'application/json',
            ],
            [
                'key' => $this->mainConfig->securityConfig->tokenName,
                'value' => '{{' . $variableApiToken->key . '}}',
            ]
        ];
    }

    protected function buildParam(Method $method, ParamDefinition $param): void
    {
        $this->postmanSpecBuilder->buildParam(
            $method,
            $param
        );
    }

}
