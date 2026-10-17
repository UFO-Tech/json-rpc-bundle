[Українська](README-UA.md)

# 10.0 — API versions, normalized docs, compile-time service map

Tags: 10.0.0, 10.0.1, 10.0.2, 10.0.3, 10.0.5, 10.0.6. First released: 2026-02-26.

## Highlights

- **API versions**: several versions of one API side by side (`/rpc`, `/rpc/v2`, ...); a new version inherits the previous one and can drop methods.
- **Normalized OpenRPC/Postman**: methods grouped by procedure, errors from `@throws`, enums and DTOs as shared `#/components/schemas` references, `info.x-versions`.
- **Service map built at compile time** (`RpcServiceMapPass`) instead of at runtime.
- **Automatic parameter conversion**: a parameter typed with a class supported by a convertor (UUID, DateTime, enum, Doctrine entity) is converted without `#[RPC\Param]`.
- **Doctrine entity convertor**: pass an entity id, receive the entity.

## What's new

### API versions

A procedure class declares its version in `#[RPC\Info]` (`version` is an integer, default `1`, published as `v1`). Version N contains all methods of lower versions, with methods of the same name replaced by the newer class, minus methods listed in `removedMethods`.

**How to use**

```php
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

#[RPC\Info('user')]                       // v1
class UserApi implements IRpcService
{
    public function get(int $id): array { /* ... */ }
    public function legacySearch(string $q): array { /* ... */ }
}

#[RPC\Info('user', version: 2, removedMethods: ['legacySearch'])]
class UserApiV2 implements IRpcService
{
    public function get(string $uuid): array { /* ... */ }   // replaces user.get in v2
}
```

| Entry point | Version |
| --- | --- |
| `POST /rpc`, `GET /rpc`, `GET /rpc/postman` | `v1` (default) |
| `POST /rpc/v2`, `GET /rpc/v2`, `GET /rpc/v2/postman` | `v2` |
| `bin/console ufo:rpc:process '<json>' --ver=v2` | `v2` |

`removedMethods` takes method names without the alias. Route names: `ufo_rpc_api_server` / `ufo_rpc_api_server_ver`, `ufo_rpc_api_postman` / `ufo_rpc_api_postman_ver`. The OpenRPC document lists all registered versions in `info.x-versions`.

### Normalized documentation

- Each method is tagged with its procedure alias (`user`) instead of the class FQCN; the class goes to the tag description.
- Method errors are generated from `@throws` in the method docblock (code and message of each exception).
- Enums are extracted into `#/components/schemas/<Enum>` and referenced by `$ref`, so one enum is described once.
- Postman: collection name `RPC: <project_name> <version> (...)`, `info.version`, a `base_url` variable; the `AccessToken` header/variable was removed, only the configured token header (`token_name`) remains.

### Compile-time service map

`RpcServiceMapPass` collects all `IRpcService` classes during container compilation and stores the map in a container parameter; `ServiceMap` only hydrates `Service` objects on demand. `ServiceMapFactory` and `ServiceLocator` were removed; RPC classes are available through the `rpc.service_locator` service locator.

### Automatic parameter conversion

If a parameter has no `#[RPC\Param]` and its type is supported by a registered convertor, the convertor's `Param` is applied automatically. Built-in convertors: `UuidConverter`, `DateTimeConverter`, `BackedEnumConvertor`, `EnumConvertor` (pure enums only), `DoctrineEntityConverter`.

**How to use**

```php
#[RPC\Info('order')]
class OrderApi implements IRpcService
{
    // request: {"method": "order.status", "params": {"order": 42}}
    public function status(Order $order): string { return $order->getStatus(); }   // Order is a Doctrine entity
}
```

In responses, properties of a returned object whose types have a convertor (entity → identifier, enum → value, UUID/DateTime → string) are converted to scalars, also when the method has no `#[RPC\ResultAsDTO]`.

Custom convertors implement `Ufo\JsonRpcBundle\ParamConvertors\IParamConvertor` (tag `rpc.param_convertor`) and now must implement `getParamAttr(string $classFQCN): Param`.

### Doctrine entity convertor

`DoctrineEntityConverter` loads an entity by id through `ManagerRegistry` and returns its identifier (or `__toString()` for `Stringable`) in responses. Since 10.0.1 it is registered only when DoctrineBundle is enabled (`#[RequiresExtension(RequiresExtension::DOCTRINE)]`).

### Service injection into RPC method arguments

10.0.0 added `#[Autowire]` support for RPC method arguments (resolved from a per-method locator, `rpc.service_argument_locator`; such arguments are hidden from the docs).

**Known issue:** from 10.0.1 on (still present in 10.2.3) `RpcServiceMapPass::processParams()` skips every typed parameter (`if ($typeName || ...) continue;`), so method-argument `#[Autowire]` is not registered. Use constructor injection.

## Changes and fixes

- DTO parameters: a value that cannot be turned into the DTO now returns an error (`ConstraintsImposedException`, "Invalid Data for call method: ...") instead of passing the raw array to the method.
- Optional enum parameter with default `null` receives `null` for an unknown value instead of an error.
- `Package` now extends `Ufo\Packages\UfoPackage` (new dependency `ufo-tech/packages`).
- 10.0.1: optional services require their Symfony extension (`RequiresExtension`).
- 10.0.2: `ufo-tech/dto-transformer` ^3.0, `ufo-tech/rpc-objects` ^3.6.
- 10.0.3: `protected_doc` checks the token only for `GET` documentation requests and reads it from the HTTP request.
- 10.0.5–10.0.6: fixes for enum schemas, parameter convertors and the service map.

## Upgrade notes

- Requirements: PHP >= 8.3, Symfony ^7.2.
- Update `ufo-tech/rpc-objects` and `ufo-tech/dto-transformer` together with the bundle (`RPC\Info` must have `version` / `removedMethods`).
- Install template route prefix changed from `/api` to `/rpc` (`install/routes/ufo_json_rpc.yaml`); existing application route files are not changed.
- Custom `IParamConvertor`: add `getParamAttr()`.
- Custom `IServiceHolder`: `getService(string $serviceName, string $version = 'v1')`, plus new `getServices(string $version)` and `getVersions()`.
- Removed: `ServiceMapFactory`, `ServiceLocator`, `ServiceMap::addService()`, `getTarget()`, `getDescription()`. Moved: `RpcFromCli` / `RpcFromHttp` to `Server\RequestPrepare\Holders`.
- `Service`: constructor requires `Info $apiClassInfo`; `name`, `procedureFQCN`, `methodName`, `procedure` are public readonly; `getResponseInfo()` returns `RPC\DTO` instead of `ResultAsDTO`. `getName()` still works; it is marked `#[Deprecated]` only in 10.3 (use the `name` property).
- `ApiController` actions renamed: `serverAction()` → `openrpc()`, `postmanAction()` → `postman()`.
- `RPC\Info(concat: ...)` is not applied to method names since 10.0 (names always use `.`). This looks unintentional; check if you use a custom `concat`.
