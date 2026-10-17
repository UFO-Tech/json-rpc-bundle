[Українська](README-UA.md)

# 10.2 — API groups: several RPC entry points in one application

Tags: 10.2.1, 10.2.3. First released: 2026-10-15.

## Highlights

- **API groups**: publish several RPC APIs (e.g. public, internal, per-BFF) from one application, each with its own URL, method map, versions and docs.
- **`#[RPC\APIGroups]`** on a class or method selects the groups a method belongs to.
- **Per-group token validation** via `security.providers`: a custom validator service or a separate token list per group.
- **Route loaders** (`Routing\ApiYamlFileLoader`, `RpcYamlFileLoader`) that adapter bundles can extend.
- Group selection for **CLI** (`--api-group`), **batch** child processes and **Messenger** (`RpcAsyncRequest(..., apiGroup:)`).

Fully backward compatible for applications that do not use groups: everything belongs to the default group `general`.

## What's new

### Multiple RPC API entry points

Example: a public product catalog for clients and an internal API for updating prices; both share the catalog listing method.

**How to use**

Import the bundle routes once per API (`config/routes/ufo_json_rpc.yaml`). `prefix` defines the URL independently of the group. `defaults.api_group` selects the group; without it the group is `general`. Existing route imports keep working.

```yaml
ufo_json_rpc:
    resource: ../../vendor/ufo-tech/json-rpc-bundle/config/router.yaml
    prefix: /rpc
    trailing_slash_on_root: false

ufo_json_rpc_internal:
    resource: ../../vendor/ufo-tech/json-rpc-bundle/config/router.yaml
    prefix: /rpc/internal
    defaults:
        api_group: internal
    trailing_slash_on_root: false

ufo_json_rpc_public:
    resource: ../../vendor/ufo-tech/json-rpc-bundle/config/router.yaml
    prefix: /rpc/public
    defaults:
        api_group: public
    trailing_slash_on_root: false
```

Route names automatically get the `general_`, `internal_` or `public_` prefix; import keys do not prefix route names. An explicit `name_prefix` is optional and goes after the group prefix. Legacy route names of the general API remain as aliases.

`POST /rpc/internal` executes requests, `GET /rpc/internal` returns OpenRPC, `GET /rpc/internal/postman` returns Postman. Versioned routes: `/rpc/internal/v2` and `/rpc/internal/v2/postman`. Each document contains only its group's methods and versions, with that entry point's URL; the Postman collection name gets a `[group]` suffix (10.2.3).

### Method groups: `#[RPC\APIGroups]`

`RPC\APIGroups(array $groups = [], bool $withDefault = true)` (from `ufo-tech/rpc-objects` ^3.9). The class below shares `catalog.list` between internal and public API, while each group also has its own methods. `false` excludes the default `general` group.

```php
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

#[RPC\Info('catalog')]
#[RPC\APIGroups(['internal', 'public'], false)]
class CatalogApi implements IRpcService
{
    public function list(): array
    {
        return [['id' => 1, 'name' => 'Coffee']];
    } // shared by internal and public API

    #[RPC\APIGroups(['internal'], false)]
    public function updatePrice(int $productId, int $price): true { return true; } // internal only

    #[RPC\APIGroups(['public'], false)]
    public function featured(): array { return [1]; } // public only

    #[RPC\APIGroups()]
    public function status(): string { return 'ok'; } // general only

    #[RPC\APIGroups(['internal'], false)]
    #[RPC\IgnoreApi]
    public function rebuildIndex(): true { return true; } // never published
}
```

| RPC method | General `/rpc` | Internal `/rpc/internal` | Public `/rpc/public` |
| --- | --- | --- | --- |
| `catalog.list` | No | Yes | Yes |
| `catalog.updatePrice` | No | Yes | No |
| `catalog.featured` | No | No | Yes |
| `catalog.status` | Yes | No | No |
| `catalog.rebuildIndex` | No | No | No |

Rules:

- A method attribute replaces the class groups. Otherwise the class groups apply; without either attribute, `new RPC\APIGroups()` (= `general`) applies.
- `withDefault: true` adds `general`; `false` does not. `APIGroups([], false)` exposes the method in no group.
- `IgnoreApi` takes precedence over groups.
- `general` is not a union of all groups.
- Service maps and version inheritance are separate per group. A method outside the selected group returns `Method not found`, without falling back to another API.
- For HTTP the group comes from routing, not from JSON request fields.

### Token validation providers

Existing security configuration and overrides of the base `ITokenValidator` remain supported. Without `providers`, the base validator applies to all groups; the standard `SimpleTokenValidator` checks `clients_tokens`.

**How to use: a validator service per group**

```yaml
ufo_json_rpc:
    security:
        protected_api: true
        protected_doc: false
        token_name: 'Ufo-RPC-Token'
        clients_tokens:
            - '%env(resolve:UFO_API_TOKEN)%'
        providers:
            internal:
                service: App\Services\RpcSecurity\InternalTokenValidator
                api_groups: [internal]
            public:
                service: App\Services\RpcSecurity\UserTokenValidator
                api_groups: [public]
```

The provider is a Symfony service implementing `Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator` (`isValid(string $token): true`); it rejects a token by throwing `RpcInvalidTokenException`.

- The assigned provider replaces base validation for its groups; a rejected token never falls back to the base validator.
- Groups without a provider use base validation.
- Two providers for one group, a missing service or a class not implementing `ITokenValidator` fail container compilation.
- `protected_api`, `protected_doc` and `token_name` stay global. Protected documentation uses its group's provider.

**How to use: a token list per group**

```yaml
ufo_json_rpc:
    security:
        protected_api: true
        protected_doc: false
        token_name: 'Ufo-RPC-Token'
        clients_tokens:
            - '%env(resolve:UFO_API_TOKEN)%'
        providers:
            internal:
                api_groups: [internal]
                clients_tokens:
                    - '%env(resolve:UFO_INTERNAL_API_TOKEN)%'
            public:
                api_groups: [public]
                clients_tokens:
                    - '%env(resolve:UFO_PUBLIC_API_TOKEN)%'
```

Each provider defines exactly one of `service` or `clients_tokens`; `api_groups` is required and non-empty. For `clients_tokens` the bundle registers a separate `SimpleTokenValidator`; no application service is needed. A list may hold several keys (token rotation). Here the internal API accepts only internal keys and the public API only public keys; `UFO_API_TOKEN` applies to `general` and other groups without a provider.

### Custom token validator without API groups

Keep the single `/rpc` import without `defaults.api_group` and RPC services without `APIGroups`. Register the validator as the base `ITokenValidator` through an alias; no `providers` block is needed:

```yaml
# config/packages/ufo_json_rpc.yaml
ufo_json_rpc:
    security:
        protected_api: true
        protected_doc: false
        token_name: 'Ufo-RPC-Token'
```

```yaml
# config/services.yaml
services:
    App\Services\RpcSecurity\UserTokenValidator:
        autowire: true

    Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator:
        alias: App\Services\RpcSecurity\UserTokenValidator
```

The validator handles every protected entry point without a group provider, including documentation when `protected_doc: true`. `clients_tokens` is optional since 10.2 (defaults to `[]`).

Alternatively, the single API implicitly belongs to `general`, so it can get an explicit provider (register the validator as a service; no alias needed):

```yaml
ufo_json_rpc:
    security:
        protected_api: true
        protected_doc: false
        token_name: 'Ufo-RPC-Token'
        providers:
            user:
                service: App\Services\RpcSecurity\UserTokenValidator
                api_groups: [general]
```

### Route loaders for API adapters

`Routing\ApiYamlFileLoader` (abstract) handles API-group validation, switching `ApiGroupContext` before route generation and restoring it after success or failure, route-name prefixes, priorities and legacy `general` aliases. `Routing\RpcYamlFileLoader` (tag `routing.loader`, priority 100) applies it to the bundle's RPC imports. Adapter bundles can extend `RpcYamlFileLoader` to keep RPC handling and recognise their own imports:

```php
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Ufo\JsonRpcBundle\Routing\RpcYamlFileLoader;

#[AutoconfigureTag('routing.loader', ['priority' => 200])]
class AdapterYamlFileLoader extends RpcYamlFileLoader
{
    protected function getRouteNamePrefixAttribute(array $config, string $path): ?string
    {
        if ($this->isResourceImport($config['resource'], $path, __DIR__.'/../../config/router.yaml')) {
            return '_adapter_name_prefix';
        }

        return parent::getRouteNamePrefixAttribute($config, $path);
    }
}
```

The returned key stores the effective group/name prefix in route defaults; `null` leaves the import untouched. Override `isApiRoute(Route $route, string $prefixAttribute): bool` when only some imported routes belong to the adapter. A loader that does not need RPC handling extends `ApiYamlFileLoader` directly.

With autowiring and autoconfiguration, the inherited constructor receives the file locator, environment and shared `ApiGroupContext`. Keep route registration in the adapter's own loader or override Symfony's `parseRoute` hook: the selected group is already active while the API's routing resource is imported.

### CLI, batch and async

```bash
bin/console ufo:rpc:process '{"jsonrpc":"2.0","id":1,"method":"catalog.updatePrice","params":{"productId":1,"price":100}}' \
    --api-group=internal --ver=v1
```

`--api-group` defaults to `general`. HTTP batch child processes keep the route's group and version. For Messenger set the originating group explicitly:

```php
$message = new \Ufo\RpcObject\RpcAsyncRequest(
    $request,
    $token,
    apiGroup: 'internal',
);
```

Messages without a group use `general`. The worker selects the group before token validation and execution and resets it afterwards, also on failure. For HTTP the group is set in `ApiGroupContext` right after routing; it stays available for deferred execution and is reset afterwards.

### Shared cache and developer responsibility

> Changed after 10.2.3: see [../10.3/README.md](../10.3/README.md)

All groups share the existing cache and cache keys. The group is not part of service metadata or response cache keys.

**Identically named RPC methods implemented by different classes in different API groups are nonstandard server usage.** Shared caching can return another implementation's cached result or metadata; the application developer is responsible for such conflicts. Use distinct RPC names for independent implementations. Duplicate names within one group and version are rejected under the existing class inheritance rules.

## Changes and fixes

- Batch requests sent to a versioned route (`/rpc/v2`) now run every item in that version (previously items used the default version).
- Async/notification response lookup uses the request's API version.
- Versioned documentation routes (`/rpc/v2`, `/rpc/v2/postman`) are now covered by `protected_doc`.
- Async child processes are started as `php <project_dir>/bin/console ...` and receive `--api-group` and `--ver`.
- 10.2.3: Postman collection name includes the group; `AbstractPostmanAdapter` extracted as a base for adapter bundles.

## Upgrade notes

- `ufo-tech/rpc-objects` ^3.9 (for `RPC\APIGroups` and `RpcAsyncRequest::$apiGroup`).
- No configuration change is required; without groups the behaviour is unchanged.
- `SimpleTokenValidator` constructor changed from `(RpcMainConfig $rpcConfig)` to `(array $tokens = [])`; subclasses or manual instantiation must be updated. The bundle wires `$tokens` from `security.clients_tokens`.
- Bundle route names are now group-prefixed (`general_ufo_rpc_api_server`, `internal_ufo_rpc_api_server`, ...). The old names (`ufo_rpc_api_server`, ...) stay as aliases only for `general`, so `router->generate()` calls keep working for the default API.
- `ServiceMap`, `ServiceHolder`, `TokenRpcSecurity`, `RpcAsyncProcessor`, `DocSecurityListener` received new optional constructor arguments (`ApiGroupContext`, etc.); custom subclasses with their own constructors should pass them through.
