<?php
namespace Ufo\JsonRpcBundle\DocAdapters\Outputs;

use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Method;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\PostmanSpecBuilder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;
use Ufo\RpcObject\RpcTransport;

use function explode;

class PostmanAdapter
{
    protected PostmanSpecBuilder $postmanSpecBuilder;

    public function __construct(
        protected ServiceMap $serviceMap,
        protected RpcMainConfig $mainConfig,
    ) {}

    public function adapt(): array
    {
        $this->buildSignature();
        $this->buildServer();
        $this->buildServices();
        return $this->postmanSpecBuilder->build();
    }

    protected function buildSignature(): void
    {
        $this->postmanSpecBuilder = PostmanSpecBuilder::createBuilder(
            $this->mainConfig->docsConfig->projectName,
            $this->serviceMap->getDescription(),
        );
    }

    protected function buildServer(): void
    {
        $http = RpcTransport::fromArray($this->mainConfig->url);
        $this->postmanSpecBuilder->addServer(
            $http->getDomainUrl() . $this->serviceMap->getTarget()
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
        $variableApiToken = $this->postmanSpecBuilder->addVariable('apiToken', '!changeMe!');
        $variableAccessToken = $this->postmanSpecBuilder->addVariable('accessToken', '!changeMe!');
        $headers = [
            [
                'key' => 'Content-Type',
                'value' => 'application/json',
            ],
            [
                'key' => $this->mainConfig->securityConfig->tokenKeyInHeader,
                'value' => '{{' . $variableApiToken->key . '}}',
            ],
            [
                'key' => 'AccessToken',
                'value' => '{{' . $variableAccessToken->key . '}}',
            ],
        ];

        $fqcn = explode('\\', $service->getProcedureFQCN());
        $tag = end($fqcn);
        $method = $this->postmanSpecBuilder->buildMethod(
            $service->getName(),
            $service->getDescription(),
            $headers,
            $tag
        );

        foreach ($service->getParams() as $param) {
            $this->buildParam($method, $param);
        }
    }

    protected function buildParam(Method $method, array $param): void
    {
        $this->postmanSpecBuilder->buildParam(
            $method,
            $param
        );
    }

}
