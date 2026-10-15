<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Routing;

use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\RpcObject\RPC\APIGroups;

/** Shared API-group handling for YAML imports used by RPC and its adapters. */
abstract class ApiYamlFileLoader extends YamlFileLoader
{
    public function __construct(
        #[Autowire(service: 'file_locator')]
        FileLocatorInterface $locator,
        #[Autowire(param: 'kernel.environment')]
        ?string $env = null,
        protected ?ApiGroupContext $apiGroupContext = null,
    ) {
        parent::__construct($locator, $env);
    }

    protected function parseImport(RouteCollection $collection, array $config, string $path, string $file): void
    {
        $prefixAttribute = $this->getRouteNamePrefixAttribute($config, $path);
        if ($prefixAttribute === null) {
            parent::parseImport($collection, $config, $path, $file);
            return;
        }

        $group = ApiGroupContext::validateGroup($config['defaults'][ApiGroupContext::ROUTE_GROUP] ?? APIGroups::DEFAULT_GROUP);
        $previousGroup = $this->apiGroupContext?->getGroup();
        $previousPrefix = $this->apiGroupContext?->getRouteName('');
        $imported = new RouteCollection();
        try {
            // Dynamic route loaders must see the group before generating routes.
            $this->apiGroupContext?->setGroup($group);
            parent::parseImport($imported, $config, $path, $file);
        } finally {
            if ($previousGroup !== null) {
                $this->apiGroupContext->setGroup($previousGroup, $previousPrefix);
            }
        }

        $prefix = $group.'_'.($config['name_prefix'] ?? '');
        foreach ($imported->all() as $name => $route) {
            if (!$this->isApiRoute($route, $prefixAttribute)) {
                continue;
            }
            $priority = $imported->getPriority($name) ?? 0;
            $imported->remove($name);
            $route->setDefault(ApiGroupContext::ROUTE_GROUP, $group);
            $route->setDefault($prefixAttribute, $prefix);
            $imported->add($group.'_'.$name, $route, $priority);
            if ($group === APIGroups::DEFAULT_GROUP) {
                $imported->addAlias($name, $group.'_'.$name);
            }
        }

        $collection->addCollection($imported);
    }

    /** Return the adapter's prefix-default key, or null to leave the import untouched. */
    abstract protected function getRouteNamePrefixAttribute(array $config, string $path): ?string;

    /** Override to group only the adapter's routes within a mixed import. */
    protected function isApiRoute(Route $route, string $prefixAttribute): bool
    {
        return true;
    }

    protected function isResourceImport(mixed $resource, string $path, string $expectedResource): bool
    {
        if (!is_string($resource) || ($expected = realpath($expectedResource)) === false) {
            return false;
        }
        try {
            $resolved = $this->locator->locate($resource, dirname($path));
        } catch (\InvalidArgumentException) {
            return false;
        }

        return is_string($resolved) && realpath($resolved) === $expected;
    }
}
