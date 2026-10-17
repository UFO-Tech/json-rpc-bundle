<?php
namespace Ufo\JsonRpcBundle\DocAdapters\Outputs;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\PostmanSpecBuilder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;

class PostmanAdapter extends AbstractPostmanAdapter
{
    protected const string PREFIX = 'RPC';
    protected const string ROUTE = ApiController::API_ROUTE;
    protected const string ROUTE_VER = ApiController::API_ROUTE_VER;

    public function __construct(
        protected ServiceMap $serviceMap,
        RpcMainConfig $mainConfig,
        RouterInterface $router,
        ApiGroupContext $apiGroupContext,
        ?RequestStack $requestStack = null,
    ) {
        parent::__construct($mainConfig, $router, $apiGroupContext, $requestStack);
    }

    protected function createSpecBuilder(string $name, string $description, string $version): PostmanSpecBuilder
    {
        return PostmanSpecBuilder::createBuilder($name, $description, $version);
    }

    protected function buildServices(): void
    {
        foreach ($this->serviceMap->getServices($this->version) as $service) {
            $this->buildService($service);
        }
    }

    protected function buildService(Service $service): void
    {
        $method = $this->postmanSpecBuilder->buildMethod(
            $service->name,
            $service->getDescription($this->requestStack?->getCurrentRequest()?->getLocale()),
            $this->buildHeaders(),
            $service->procedure
        );

        foreach ($service->getParams() as $param) {
            $this->buildParam($method, $param);
        }
    }

}
