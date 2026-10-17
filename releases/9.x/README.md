[Українська](README-UA.md)

# 9.x

Major 9 (2025-10-01 — 2026-01-20) rebuilds the bundle's type handling on `ufo-tech/dto-transformer` 2: parameter and return types are resolved from native types and docblocks into one JSON Schema, with proper support for enums, union types and DTO collections in both OpenRPC docs and runtime conversion. 9.1 turns the `async` section into a list of typed transports and moves the response cache to a dedicated, configurable pool with a cache-relations API and a per-request cache bypass. 9.2 makes `$rpc.callback` requests non-blocking and adds a lightweight `?info` mode for the documentation endpoint.

## Requirements

| | 9.0.0 | 9.2.1 |
|---|---|---|
| PHP | >= 8.3 | >= 8.3 |
| Symfony (`framework-bundle` and components) | ^7.2 | ^7.2 |
| `ufo-tech/dto-transformer` | ^2 | ^2 |
| `ufo-tech/rpc-objects` | ^3.3 | ^3.4 |
| `ufo-tech/rpc-exceptions` | ^1.3 | ^1.3 |

## Versions

| Version | Highlights |
|---|---|
| [9.0](9.0/README.md) | Type system on dto-transformer 2: unified JSON Schema from native + docblock types, enums, union types, DTO collections, DTO defaults/required in OpenRPC, refactored constraint generators |
| [9.1](9.1/README.md) | List of typed async transports, dedicated `rpc.cache.pool` with `cache: { ttl, prefix }`, deprecated methods in OpenRPC, cache-relations API, `$rpc.ignoreCache` |
| [9.2](9.2/README.md) | Non-blocking `$rpc.callback` requests with header forwarding, `GET ?info` for lightweight docs |

## Upgrading from 8.x

1. Composer: `ufo-tech/dto-transformer` ^2 (from ^1.1) and `ufo-tech/rpc-objects` ^3.4 (from ^3.1). Review your own `#[DTO]` / `#[ResultAsDTO]` attributes against the new APIs (the bundle itself moved to `#[DTOCollection(...)]` and `context: [...]` options).
2. Custom constraint generators: `Genearator` → `Generator`; `IConstraintGenerator::generate(Constraint, array &$rules, ?Generator $generator = null)`; `IsType` removed.
3. Internal API changes if you extend the bundle: `Service::getParamDto()` and `Service::addReturn()` removed, `getParamsDto()` returns `array<string, DtoReflector[]>`, changed constructors of `ParamDefinition`, `JsonSchemaNormalizer`, `ChainParamConvertor`.
4. `async` config: rewrite to a list of `{type, config: {name, dsn, ...}}`; the `failed` key is no longer supported. OpenRPC `x-ufo.transport` keys async transports by `name` instead of `async`.
5. Add a `cache: { ttl, prefix }` section; the response cache now lives in its own filesystem pool `rpc.cache.pool`.
6. `RpcCacheService::invalidateRelationsCache()` → `addRelationsRequestToCache()`.
7. `$rpc.callback` requests: the immediate result is now `{"async": true, "callback": "<url>"}`; the method runs after the response (requires PHP-FPM for HTTP), and the caller's headers — including the RPC token — are forwarded to the callback URL.
8. In 9.2.1 the OpenRPC output no longer marks methods as `deprecated`.
9. Clear the Symfony cache after upgrading.
