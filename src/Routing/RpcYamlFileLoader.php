<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Routing;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\RouteCollection;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\RpcObject\RPC\APIGroups;

#[AutoconfigureTag('routing.loader', ['priority' => 100])]
class RpcYamlFileLoader extends YamlFileLoader
{
    protected function parseImport(RouteCollection $collection, array $config, string $path, string $file): void
    {
        $imported = new RouteCollection();
        parent::parseImport($imported, $config, $path, $file);

        // Controller imports stay untouched; group names are applied at the API import.
        $resource = $config['resource'];
        $isRpcImport = is_string($resource)
            && realpath(str_starts_with($resource, '/') ? $resource : dirname($path).'/'.$resource) === realpath(__DIR__.'/../../config/router.yaml');
        if ($isRpcImport || isset($config['defaults'][ApiGroupContext::ROUTE_GROUP])) {
            $group = $config['defaults'][ApiGroupContext::ROUTE_GROUP] ?? APIGroups::DEFAULT_GROUP;
            $prefix = $group.'_'.($config['name_prefix'] ?? '');
            foreach ($imported->all() as $name => $route) {
                if (!$route->getDefault('_rpc_route')) {
                    continue;
                }
                $priority = $imported->getPriority($name) ?? 0;
                $imported->remove($name);
                $route->setDefault(ApiGroupContext::ROUTE_GROUP, $group);
                $route->setDefault(ApiGroupContext::ROUTE_NAME_PREFIX, $prefix);
                $imported->add($group.'_'.$name, $route, $priority);
                if ($group === APIGroups::DEFAULT_GROUP) {
                    $imported->addAlias($name, $group.'_'.$name);
                }
            }
        }

        $collection->addCollection($imported);
    }
}
