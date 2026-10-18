<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Routing;

/**
 * Registers an adapter's router.yaml with the RPC YAML loader instead of extending the loader:
 * every import of it gets API-group route names ("<group>_<name>") and the "<group>_<name_prefix>"
 * route default named by getRouteNamePrefixAttribute(), so the same router.yaml can be imported once per group.
 * Works whichever loader subclass wins the "routing.loader" priority.
 * Autoconfigured implementations are tagged automatically (UfoJsonRpcExtension).
 */
interface IApiRouteImport
{
    public const string TAG = 'ufo.rpc.route_import';

    /** Absolute path to the adapter's router.yaml. */
    public function getResource(): string;

    /** Route default that receives the route name prefix, e.g. "_mcp_name_prefix". */
    public function getRouteNamePrefixAttribute(): string;
}
