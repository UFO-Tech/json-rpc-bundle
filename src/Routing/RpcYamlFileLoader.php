<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Routing;

use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Routing\Route;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;

#[AutoconfigureTag('routing.loader', ['priority' => 100])]
class RpcYamlFileLoader extends ApiYamlFileLoader
{
    /**
     * @param iterable<IApiRouteImport> $routeImports
     */
    public function __construct(
        #[Autowire(service: 'file_locator')]
        FileLocatorInterface $locator,
        #[Autowire(param: 'kernel.environment')]
        ?string $env = null,
        ?ApiGroupContext $apiGroupContext = null,
        #[AutowireIterator(IApiRouteImport::TAG)]
        protected iterable $routeImports = [],
    ) {
        parent::__construct($locator, $env, $apiGroupContext);
    }

    protected function getRouteNamePrefixAttribute(array $config, string $path): ?string
    {
        // Registered adapters first: their imports also carry api_group, which would otherwise match the RPC branch.
        foreach ($this->routeImports as $routeImport) {
            if ($this->isResourceImport($config['resource'], $path, $routeImport->getResource())) {
                return $routeImport->getRouteNamePrefixAttribute();
            }
        }

        // Controller imports stay untouched; group names are applied at the API import.
        if ($this->isResourceImport($config['resource'], $path, __DIR__.'/../../config/router.yaml')
            || isset($config['defaults'][ApiGroupContext::ROUTE_GROUP])) {
            return ApiGroupContext::ROUTE_NAME_PREFIX;
        }

        return null;
    }

    protected function isApiRoute(Route $route, string $prefixAttribute): bool
    {
        return $prefixAttribute === ApiGroupContext::ROUTE_NAME_PREFIX
            ? (bool) $route->getDefault('_rpc_route')
            : parent::isApiRoute($route, $prefixAttribute);
    }
}
