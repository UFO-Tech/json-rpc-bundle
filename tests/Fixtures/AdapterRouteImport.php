<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures;

use Ufo\JsonRpcBundle\Routing\IApiRouteImport;

/** An adapter router.yaml registered with the RPC YAML loader (as mcp-bundle would do). */
class AdapterRouteImport implements IApiRouteImport
{
    public function getResource(): string
    {
        return __DIR__.'/route_import/router.yaml';
    }

    public function getRouteNamePrefixAttribute(): string
    {
        return '_adapter_name_prefix';
    }
}
