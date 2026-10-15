<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Routing;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\Route;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;

#[AutoconfigureTag('routing.loader', ['priority' => 100])]
class RpcYamlFileLoader extends ApiYamlFileLoader
{
    protected function getRouteNamePrefixAttribute(array $config, string $path): ?string
    {
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
