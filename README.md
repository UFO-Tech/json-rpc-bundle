# JsonRpcBundle
![Ukraine](https://img.shields.io/badge/Glory-Ukraine-yellow?labelColor=blue)

JSON-RPC 2.0 server for Symfony v.7.2 and later

### About this package

This package makes it easy to create APIs using a JSON-RPC server.

>Creating RPC services for service-oriented architecture on Symfony has never been so easy.

![License](https://img.shields.io/badge/license-MIT-green?labelColor=7b8185) 
![Size](https://img.shields.io/github/repo-size/ufo-tech/json-rpc-bundle?label=Size%20of%20the%20repository) 
![package_version](https://img.shields.io/github/v/tag/ufo-tech/json-rpc-bundle?color=blue&label=Latest%20Version&logo=Packagist&logoColor=white&labelColor=7b8185) 
![fork](https://img.shields.io/github/forks/ufo-tech/json-rpc-bundle?color=green&logo=github&style=flat)

### Environment Requirements
![php_version](https://img.shields.io/packagist/dependency-v/ufo-tech/json-rpc-bundle/php?logo=PHP&logoColor=white) 
![symfony_version](https://img.shields.io/packagist/dependency-v/ufo-tech/json-rpc-bundle/symfony/framework-bundle?label=Symfony&logo=Symfony&logoColor=white) 
![symfony_version](https://img.shields.io/packagist/dependency-v/ufo-tech/json-rpc-bundle/symfony/serializer?label=SymfonySerializer&logo=Symfony&logoColor=white) 
![symfony version](https://img.shields.io/packagist/dependency-v/ufo-tech/json-rpc-bundle/symfony/serializer?label=SymfonyCache&logo=Symfony&logoColor=white)

This package allows you to easily create APIs using a JSON-RPC server for Symfony v.7.2 and newer versions (PHP >= 8.3). It is suitable for developers who want to quickly and effectively integrate RPC functionality into their projects.

### Key Benefits
- **Ease of Integration:** Integrating this package into your project is extremely simple. You just need to add a special interface to any existing class, and it will automatically gain the ability to handle JSON-RPC requests.
- **Flexibility:** The package provides great flexibility in API creation, allowing developers to easily extend and modify server behavior without interfering with existing code.
- **Efficiency:** Thanks to the optimization of calls and the use of modern Symfony components, the package ensures high performance in handling requests.

## [Documentation](https://docs.ufo-tech.space/bin/view/docs/JsonRpcBundle/?language=en)
For comprehensive documentation on installation, configuration, and use of this library, see the documentation portal [UFO-Tech](https://docs.ufo-tech.space/?language=en) (Universal Flexible Open Technologies)

[JsonRpcBundle](https://docs.ufo-tech.space/bin/view/docs/JsonRpcBundle/?language=en)


## Multiple RPC API entry points

For example, expose a public product catalog to clients and an internal API
for updating prices. Both APIs share the catalog listing method.

Import the bundle routes once per API. `prefix` defines the URL independently
of the group. `defaults.api_group` selects the group; omitting it selects
`general`. Existing route imports continue to work.

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

Route names automatically receive `general_`, `internal_`, or `public_` prefixes.
Import keys do not prefix route names. An explicit `name_prefix` is optional
and follows the group prefix. Legacy general API route names remain aliases.

`POST /rpc/internal` executes requests, `GET /rpc/internal` returns OpenRPC, and
`GET /rpc/internal/postman` returns Postman. Versioned routes use `/rpc/internal/v2`
and `/rpc/internal/v2/postman`. Each document contains only its group's methods
and versions, with that entry point's URL.

### Route loaders for API adapters

`Routing\ApiYamlFileLoader` owns API-group validation, context switching before
route generation, restoration after success or failure, route-name prefixes,
priorities and legacy `general` aliases. Adapters can extend `RpcYamlFileLoader`
to retain RPC import handling and add their own import recognition:

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

The returned key stores the effective group/name prefix in route defaults;
`null` leaves the import untouched. Override `isApiRoute(Route $route, string
$prefixAttribute): bool` when only some imported routes belong to the adapter.
For a loader that does not need RPC handling, extend `ApiYamlFileLoader` directly.

With autowiring and autoconfiguration enabled, the inherited constructor supplies
the file locator, environment and shared `ApiGroupContext`; adapters do not need
to repeat these arguments in service configuration. Keep route registration in
the adapter's own Symfony loader or override Symfony's `parseRoute` hook: the
selected group is already active while the API's routing resource is imported.

### Method groups

The class below shares `catalog.list` between internal and public API, while
each group also has its own methods. `false` excludes the default `general`
group from the class.

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

A method attribute replaces its class groups. Otherwise class groups apply;
without either attribute, `new RPC\APIGroups()` applies. `withDefault: true`
adds `general`; `false` disables that addition. `APIGroups([], false)` exposes
no groups. `IgnoreApi` takes precedence over groups. The general group does
not automatically include all methods.

Service maps and version inheritance are separate per group. A method outside
the selected group returns `Method not found`, without falling back to another
API. HTTP groups come from routing, not JSON request fields.

### Token validation providers

Existing security configuration and overrides of the base `ITokenValidator`
remain supported. Without `providers`, the base validator applies to all groups.
The standard validator checks `clients_tokens`.

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

Register the provider as a Symfony service implementing
`Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator` with
`isValid(string $token): true`. Reject invalid tokens by throwing
`RpcInvalidTokenException`.

The assigned provider replaces base validation for its groups. Rejected tokens
never fall back to the base validator. Groups without a provider use base
validation. Duplicate providers for a group, missing services, and invalid
interfaces fail container compilation. `protected_api`, `protected_doc`, and
`token_name` remain global. Protected documentation uses its group's provider.

For simple token validation, assign separate environment keys to each group
instead of registering custom services:

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

Each provider must define exactly one of `service` or `clients_tokens`.
For `clients_tokens`, the bundle creates a separate `SimpleTokenValidator`;
no application service registration is needed. A list can contain several keys
for token rotation. An empty list rejects every token without falling back.

Here, internal API accepts only its internal keys and public API only its public
keys. The base `UFO_API_TOKEN` applies to `general` and other groups without an
assigned provider. The same provider selection applies to protected documentation.

### Custom token validator without API groups

Keep the single `/rpc` route import without `defaults.api_group` and leave RPC
services without `APIGroups` attributes. Register your custom validator as the
base `ITokenValidator` through a Symfony service alias; no `providers` block
is needed:

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

The custom validator handles all protected entry points without an assigned
group provider, including protected documentation when `protected_doc: true`.
`clients_tokens` is unnecessary unless your custom validator uses it.

Alternatively, the single API implicitly belongs to `general`, so it can use
an explicitly assigned provider:

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

In this alternative, register the validator as a Symfony service; an interface
alias is unnecessary. Omitting `api_groups` or setting it to `[]` assigns the
provider to no groups; it does not make it the base validator.

### CLI, batch, and async

CLI accepts `--api-group=internal`, defaulting to `general`. HTTP batch child
processes retain the route's group and version. For Messenger, explicitly set
the originating group:

```php
$message = new \Ufo\RpcObject\RpcAsyncRequest(
    $request,
    $token,
    apiGroup: 'internal',
);
```

Messages without a group use `general`. The worker selects the group before
validation and execution, then resets it even on failure. HTTP routing sets
the group directly in `ApiGroupContext` before validation and execution. The
context remains available for deferred execution and resets afterwards.

### Shared cache and developer responsibility

All groups share the existing cache and cache keys. No group is added to service
metadata or response cache keys.

**Identically named RPC methods implemented by different classes in different
API groups are nonstandard server usage.** Shared caching can return another
implementation's cached result or metadata. The application developer using the
server is responsible for these conflicts and result correctness. Use distinct
RPC names for independent implementations. Duplicate names within a group and
version are rejected under the existing class inheritance rules.
