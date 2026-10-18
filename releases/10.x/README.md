[Українська](README-UA.md)

# ufo-tech/json-rpc-bundle 10.x

Major 10 turns the bundle into a multi-API server: versioned APIs (10.0), JSON-RPC notifications and Symfony 8 (10.1), several API groups / entry points with their own tokens (10.2), a performance and batch release (10.3), and translations per API group and from extensions (10.4). The service map is built at compile time, documentation (OpenRPC, Postman) is normalized and published per version and per group.

10.3 is a minor release but contains a few backward-incompatible changes for code that constructs or extends bundle services and behaviour changes for batches: see its [Upgrade notes](10.3/README.md#upgrade-notes).

## Requirements

| | 10.0.0 | 10.2.3 |
| --- | --- | --- |
| PHP | >= 8.3 | >= 8.4 |
| Symfony | ^7.2 | ^7.2 \| ^8.0 |
| ufo-tech/rpc-objects | ^3.4 (^3.6 from 10.0.2) | ^3.9 |
| ufo-tech/dto-transformer | ^2.1 (^3.0 from 10.0.2) | ^3.1 |
| ufo-tech/rpc-exceptions | ^1.3 | ^2 |
| Other | new `ufo-tech/packages` ^1 | `symfony/messenger` now required |

## Versions

| Version | Highlights |
| --- | --- |
| [10.0](10.0/README.md) | API versions (`/rpc/v2`, `RPC\Info(version:, removedMethods:)`); normalized OpenRPC/Postman (procedure tags, errors from `@throws`, shared enum schemas, `x-versions`); compile-time service map; automatic parameter conversion; Doctrine entity convertor |
| [10.1](10.1/README.md) | Symfony 8; JSON-RPC notifications (`204`, executed after the response); strict `jsonrpc: "2.0"` and empty-batch checks; unified per-parameter errors |
| [10.2](10.2/README.md) | API groups: several RPC entry points (public/internal/BFF) via route imports and `#[RPC\APIGroups]`; per-group token providers (`security.providers`); route loaders for adapters; `--api-group` for CLI and `apiGroup` for Messenger |
| [10.3](10.3/README.md) | Batch config with inline mode; JSON-RPC notification rules in batch; docs cache with `ETag` and translated method titles via `Accept-Language`; response cache keyed by implementation; 2.4–4.2× throughput |
| [10.4](10.4/README.md) | Method titles/descriptions per API group (`translations/rpc/<group>.<locale>.yaml`); translations from extensions (`translations.paths`); public `RpcLocaleNegotiator` |

## Upgrading from 9.x

Breaking changes in 10.0:

- **Interfaces**: `IServiceHolder::getService(string $serviceName, string $version = 'v1')` plus new `getServices(string $version)` and `getVersions()`; `IParamConvertor` requires `getParamAttr(string $classFQCN): Param`.
- **Removed classes/methods**: `ServiceMapFactory`, `ServiceLocator`, `ServiceMap::addService()`, `ServiceMap::getTarget()`, `ServiceMap::getDescription()`. The service map is built by `RpcServiceMapPass` at compile time.
- **Moved**: `Server\RequestPrepare\RpcFromCli` / `RpcFromHttp` → `Server\RequestPrepare\Holders\...`.
- **`Service`**: constructor requires `Info $apiClassInfo`; `name`, `procedureFQCN`, `methodName`, `procedure` are public readonly properties; `getResponseInfo()` returns `RPC\DTO` instead of `ResultAsDTO`. `getName()` keeps working in 10.x; it is marked `#[Deprecated]` in 10.3 (use `$service->name`), removal planned for 11.0.
- **`ApiController`** actions renamed `serverAction()` → `openrpc()`, `postmanAction()` → `postman()`; new versioned routes `ufo_rpc_api_server_ver`, `ufo_rpc_api_postman_ver`.
- **Documentation output changed**: tags are procedure aliases, errors come from `@throws`, enums are `$ref`s, Postman collection name and variables changed (`AccessToken` removed). Tools that parse the documents may need updates.
- **`Package`** now extends `Ufo\Packages\UfoPackage` (static helpers are inherited; new dependency `ufo-tech/packages`).
- **Install template** route prefix `/api` → `/rpc` (your existing route file is not changed).
- **Behaviour**: invalid DTO parameters now return an error instead of passing the raw array; `RPC\Info(concat: ...)` is no longer applied to method names (looks unintentional). **Fixed in [10.3.1](10.3/README.md#1031).**

Later minors add requirements and smaller breaks: PHP 8.4 and `rpc-exceptions` ^2 in [10.1](10.1/README.md#upgrade-notes), `SimpleTokenValidator` constructor and group-prefixed route names in [10.2](10.2/README.md#upgrade-notes), constructor and batch changes in [10.3](10.3/README.md#upgrade-notes), the partial-translation fallback in [10.4](10.4/README.md#upgrade-notes).

Known issue across 10.0.1–10.2.3: `#[Autowire]` on RPC method arguments is not registered (see [10.0](10.0/README.md#service-injection-into-rpc-method-arguments)); use constructor injection. **Fixed in [10.3.1](10.3/README.md#1031).**
