[Українська](README-UA.md)

# 9.1 — Multiple async transports, dedicated response cache, deprecated methods

Tags: 9.1.0, 9.1.1, 9.1.2, 9.1.3, 9.1.4, 9.1.5. First released: 2025-11-17.

## Highlights

- `async` is now a list of typed transport configs (`type` + `config`); several transports can be declared side by side (9.1.0).
- Response cache moved to its own filesystem pool `rpc.cache.pool`, configured by the new `cache: { ttl, prefix }` section (9.1.1).
- Methods marked `@deprecated` (docblock) or `#[\Deprecated]` are flagged as deprecated in OpenRPC (9.1.1).
- Cache relations: programmatic warm-up and invalidation via `RpcCacheService::warmCache()` / `deleteRelationCache()` (9.1.1, 9.1.5).
- Per-request cache bypass with the special parameter `$rpc.ignoreCache` (9.1.3).

## What's new

### Multiple async transports (9.1.0)

The `async` section changed from a fixed map (`rpc_async`, `failed`) to a list. Each entry has a `type` and a free-form `config` (`name`, `dsn`, optional `route`, plus any extra keys). Entries with `type: amqp` are registered as Symfony Messenger transports: `name` becomes the transport name, and `RpcAsyncRequest` (or `config.route`) is routed to it. Entries of other types are not wired to Messenger by the bundle; they are kept in `RpcAsyncConfig::$rpcAsync` (retrievable with `RpcAsyncConfig::getConfig(<type>)`) and, when `docs.async_dsn_info: true`, listed in the OpenRPC `x-ufo.transport` block under their `name` with the parsed DSN merged with the extra keys.

**How to use:**

```yaml
# config/packages/ufo_json_rpc.yaml
ufo_json_rpc:
    async:
        - type: 'amqp'
          config:
              name: 'rpc_async'
              dsn: '%env(resolve:RPC_TRANSPORT_DSN)%'
        - type: 'mercure'
          config:
              name: 'rpc_socket'
              dsn: '%env(resolve:MERCURE_PUBLIC_URL)%'
              topics_prefix: 'rpc.event.'
```

The legacy single-entry map `async: { rpc_async: '<dsn>' }` is still normalized into one `amqp` entry, but only its first key is used.

### Dedicated cache pool and `cache` section (9.1.1)

`RpcCacheService` now uses its own service `rpc.cache.pool` (`FilesystemAdapter` in `%kernel.cache_dir%`) instead of the autowired application cache. Its namespace and default lifetime come from the new config section.

**How to use:**

```yaml
ufo_json_rpc:
    cache:
        ttl: 60              # default lifetime, seconds
        prefix: 'rpc_cache'  # cache namespace
```

The cache key is now computed from recursively sorted params (`sortRecursive()`), so key order in nested objects does not change the key. Note that list values are sorted too.

### Deprecated methods in OpenRPC (9.1.1)

`DescriptionFiller` marks a method deprecated if its docblock has `@deprecated`; `AttributesFiller` does the same for the PHP `#[\Deprecated]` attribute (native since PHP 8.4). `OpenRpcSpecBuilder::buildMethod()` writes `deprecated: true` for such methods.

**How to use:**

```php
/**
 * @deprecated use Orders.create
 */
public function createOrder(array $items): array { /* ... */ }
```

### Cache relations API (9.1.1, 9.1.5)

`RpcCacheService` gained public helpers for `#[RPC\CacheRelation]` scenarios:

- `warmCache(string $class, array $methods, array $params, bool $deleteCache = true)` — builds related requests for the given procedure methods, optionally drops their cached responses and executes them again to refill the cache (9.1.1).
- `deleteRelationCache(string $class, array $methods, array $params)` — only drops cached responses of the related requests (9.1.5).
- `deleteRequestCache(RpcRequest $request)` — drops the cached response of one request (9.1.5).
- `invalidateRelationsCache()` was renamed to `addRelationsRequestToCache(array $params, string $id, bool $deleteCache = true)`.

**How to use:**

```php
public function __construct(private RpcCacheService $rpcCache) {}

// after an entity update
$this->rpcCache->deleteRelationCache(OrderProcedure::class, ['get', 'list'], ['id' => $id]);
```

### `$rpc.ignoreCache` special parameter (9.1.3)

`RpcServer::handle()` skips the cached response when the request's special params contain `ignoreCache: true`; the response is computed and saved again. Requires `ufo-tech/rpc-objects` ^3.4. Warm-up requests built by the cache relations API set this flag automatically.

**How to use:**

```json
{"id": 1, "method": "Orders.get", "params": {"id": 10, "$rpc": {"ignoreCache": true}}}
```

## Changes and fixes

- 9.1.1 — OpenRPC `x-ufo.documentation` contains a `postman` link (`<server url>/postman`). `RpcAsyncProcessor::processAsync(RpcRequest)` extracted from `__invoke()` (token check stays in `__invoke()`). `RpcEventFactory::fire()` accepts an event object as well as an event name. `ParamsConverterEventListener` passes `null` values to the param convertor again (9.0.6 skipped them as non-scalar). Config files reformatted to 2-space YAML. `RpcServer::handle()` got an `ignoreCache` argument (replaced in 9.1.3).
- 9.1.2 — Fixed `warmCache()` when the procedure reflection was not yet cached (reflection variable was not assigned).
- 9.1.3 — `ufo-tech/rpc-objects` ^3.4; `RpcServer::handle(RpcRequest $singleRequest)` lost the `ignoreCache` argument in favour of `$rpc.ignoreCache`.
- 9.1.4 — `RequestCarrier` implements the extended `IRpcSpecialParamHandler` (`setParam()`, `resetParams()`); special params of the incoming request are parsed and validated when the carrier is set.
- 9.1.5 — `deleteRelationCache()` and `deleteRequestCache()` added; relation iteration refactored; deprecated `invalidateRelationsCache()` removed.

## Upgrade notes

- `async` config: rewrite to the list form shown above. The `failed` key is no longer supported; declare a failure transport in `framework.messenger` yourself if you need one. Code reading `RpcAsyncConfig::$rpcAsync` gets an array of `RpcAsyncInfo` instead of a DSN string, and `RpcAsyncConfig::$failed` is gone. In OpenRPC `x-ufo.transport`, async transports are keyed by their `name` (e.g. `rpc_async`) instead of `async`.
- Add the `cache:` section (as in `install/packages/ufo_json_rpc.yaml`): `rpc.cache.pool` reads the `ufo_json_rpc.cache.ttl` / `ufo_json_rpc.cache.prefix` parameters, which are only defined when the section is present. Responses cached in the old application pool are not reused.
- `RpcCacheService::invalidateRelationsCache()` → `addRelationsRequestToCache()` (removed in 9.1.5).
- `ufo-tech/rpc-objects` ^3.4 is required from 9.1.3.
