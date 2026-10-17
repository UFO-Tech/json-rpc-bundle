[Українська](README-UA.md)

# 10.3 — Performance, batch modes, notification rules, documentation cache and translations

Tags: 10.3.0, 10.3.1. First released: 2026-10-18.

> **Note:** although this is a minor release, it contains a few backward-incompatible changes for code that constructs or extends bundle services manually, and behaviour changes for batches. Read the [Upgrade notes](#upgrade-notes) before updating.

## Highlights

- **2.4–4.2× more requests per second** for regular calls and documentation (volume benchmark below).
- **Batch configuration**: disable batches, run them inline (~250× faster than process mode) or limit parallel child processes.
- **JSON-RPC notification rules**: notifications never appear in a batch response; a batch of only notifications returns `204`.
- **Method titles and descriptions translated** by `Accept-Language` in OpenRPC/Postman.
- **Documentation cached** with `ETag` / `304 Not Modified`.
- **Security**: the token and the request body are no longer passed in the command line of child processes.

## What's new

### Translated method titles and descriptions

Put translations in `translations/rpc.<locale>.yaml` (or `.yml`) of the application. Keys are RPC method names:

```yaml
# translations/rpc.uk.yaml
catalog.list:
    title: Каталог
    description: Список товарів
```

The documentation (`GET /rpc`, `GET /rpc/postman`) picks the locale from the `Accept-Language` header among the locales that have translation files; without a match it uses `kernel.default_locale`. Responses get `Content-Language` and `Vary: Accept-Language`. The method title is published as OpenRPC `summary`. `Service::setTitle()` sets the title (a `changeTitle()` method existed only in unreleased development code).

### Documentation cache with ETag

In non-debug environments the generated OpenRPC/Postman document is cached and served with an `ETag`; a client sending `If-None-Match` gets `304 Not Modified` without generation. The cache key includes route, version, `?info`, API group, host, locale, a fingerprint of the service map and the container build id, so it is refreshed on any method change and on every container rebuild. Disabled in debug. Nothing to configure.

### Batch configuration

```yaml
ufo_json_rpc:
  batch:
    enabled: true       # false: batch requests are rejected with one error (-32600, "Batch requests are disabled")
    process: true       # true: every item in its own `ufo:rpc:process` child process; false: one by one in the current process
    process_count: 2    # process mode: child processes at the same time (1–10), the rest wait
```

- `process: false` (inline) avoids starting a PHP process per item and is ~250× faster for short methods; items run sequentially.
- `process: true` keeps items isolated and parallel; `process_count` caps how many children run at once.
- `@FROM:<id>(<field>)` dependencies work in both modes.
- Modes are strategies (`Ufo\JsonRpcBundle\Server\Batch\IBatchStrategy`, tag `rpc.batch_strategy`): `ProcessBatchStrategy`, `InlineBatchStrategy`.

### Notifications in batch (JSON-RPC 2.0)

- A single notification returns `204` without a body and runs after the response (as before).
- In a batch a notification is executed according to the batch mode but gets **no** response object; the response contains only the other methods.
- A batch of only notifications returns `204` without a body.
- A notification whose `@FROM` dependency failed is neither executed nor answered.

### Optional detached notifications

```yaml
ufo_json_rpc:
  notification:
    process: false      # true: notifications and $rpc.callback run in a `ufo:rpc:process` detached from the HTTP worker
    process_count: 2    # detached processes at the same time (1–10); when all are busy it runs inline
```

When enabled, the worker hands the request to a detached process and the request is executed **exactly once** (a hand-over ticket decides who runs it; if the process does not take it within 3 s, the worker runs it itself). Batch notifications are detached too. **Use only for rare, long-running notifications**: every detached process boots a Symfony kernel (~200–300 ms CPU), so under load it is slower than the default inline execution (see the benchmark below).

### Custom cache pool

```yaml
ufo_json_rpc:
  cache:
    pool: 'cache.app'   # any cache pool service, e.g. a shared Redis pool; default: local filesystem pool
```

Used by the response cache (`#[RPC\Cache]`) and the documentation cache. With several servers a shared pool makes `#[CacheRelation]` invalidation work on all of them. `ttl` and `prefix` apply only to the default pool.

## Changes and fixes

- **Response cache**: the key now includes the method implementation (`Class::method`) and the API version, so different implementations or versions under one method name no longer share results; methods without `#[RPC\Cache]` do no cache I/O at all.
- **Metadata cache** (`RpcMetadataCache`, `cache.system`, disabled in debug): hydrated method metadata (~1–3 ms per request before) and runtime DTO formats are no longer rebuilt on every request.
- **`kernel.terminate` / `console.terminate`**: the bundle no longer stops propagation, so application terminate listeners (mail, log flush, Sentry, …) run after RPC requests too.
- **Batch child processes**: no busy-wait loop (it used 100% of a CPU core while waiting); the token goes through env `UFO_RPC_TOKEN` and the request through stdin, not the command line; a process-mode batch works without a token on an unprotected API.
- **`@FROM` in process mode**: an integer result of an item stays an integer when substituted into another item (it became a float before).
- **Notifications without PHP-FPM**: a notification is executed even if the client has closed the connection (it was silently skipped on Apache mod_php / the built-in server); execution is logged with `LoggerInterface::info()` instead of `echo`.
- `PRE_RESPONSE` listener registered once per `RpcServer` (it accumulated in long-running workers); the RPC route is matched once per request; an unused `cache.app` lookup per method call was removed.
- `ufo:rpc:process` reads the request from stdin (`-`) or from env `UFO_RPC_REQUEST` (`@env`); passing the JSON as the argument still works.
- Requires `ufo-tech/rpc-objects` `^3.9.1`.

### Volume benchmark

Built-in PHP server with 4 workers, 8 concurrent clients, opcache on, ~49 000 requests, 0 errors. Requests per second (higher is better):

| Scenario | 10.2.3 | 10.3.0, defaults | 10.3.0, `batch.process: false` + `notification.process: true` |
|---|---|---|---|
| ping | 215 | **808** | 775 |
| method returning `object` | 180 | **736** | 791 |
| validation + named params | 191 | **799** | 748 |
| notification (fast) | 324 | **769** | 2.5 |
| notification (100 ms) | 39.5 | 39.4 | 14.4 |
| OpenRPC document | 388 | **1105** | 883 |
| batch of 10 | 2.2 | 1.9 | **568** |

## 10.3.1

Four long-standing bugs fixed (none of them was introduced in 10.3):

- **XHR requests are executed.** A POST with `X-Requested-With: XMLHttpRequest` (e.g. jQuery `$.ajax`) got an empty `200` and the method was not called (since 8.0). Only a CORS preflight `OPTIONS` gets the empty response now.
- **`#[Autowire]` on RPC method arguments works again** (broken since 10.0.1): services are injected for named params, positional params and methods without params.
  ```php
  public function report(int $id, #[Autowire(service: 'app.report_builder')] ReportBuilder $builder): array
  ```
  The argument is not an RPC param: it is not in the documentation and the client does not send it.
- **`#[RPC\Info(concat: …)]` is applied again** (ignored since 10.0): `#[RPC\Info('cc', concat: '_')]` gives `cc_x` instead of `cc.x`. **If you set `concat` and your clients call the methods with `.`, the method names change** — update the clients or remove `concat`.
- **Deprecated methods are marked in OpenRPC again** (lost in 9.2.1): methods with `@deprecated` or `#[\Deprecated]` get `"deprecated": true`.

## Upgrade notes

- **Constructors** (only if you create these services manually or extend them):
  - `RpcAsyncProcessor`: `ApiGroupContext` and `RpcMainConfig` are required and come before `$projectDir`; new optional `RpcDetachedSlots`, `LoggerInterface`.
  - `RpcRequestHandler`: `RpcMainConfig` is required; new optional `iterable $batchStrategies`.
  - `ParamsConverterEventListener`, `ServiceMap`, `AsyncServerEventListener`, `SymfonyFlowListener`: new optional trailing arguments.
- **Removed** `EventDrivenModel\Listeners\DocSecurityListener` — merged into `ApiDocListener` (documentation access, locale, cache).
- **Deprecated**: `ServiceHolder::generateServiceCacheName()` and `SERVICE_CACHE_PREFIX` (no longer read); the `CacheItemPoolInterface` argument of `ServiceHolder` is unused. Removal planned for the next major.
- **Response cache key format changed**: existing cached responses are not found once and are filled again.
- **`batch.process_count` defaults to 2**: process-mode batches run at most 2 children at a time, which makes large batches slower than before. Use `process_count: 10` or `process: false`.
- **Notifications in batch** no longer produce error entries in the response; a batch of only notifications returns `204`.
- **Application `kernel.terminate` listeners now run after RPC requests** — check listeners that were silently skipped before.
- The Messenger consumer no longer prints `>>>` / `<<<` lines; the same information is logged at `info` level.
