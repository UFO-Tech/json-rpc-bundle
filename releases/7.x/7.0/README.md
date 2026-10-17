[Українська](README-UA.md)

# 7.0 — Event-driven core and native OpenRPC

Tags: 7.0.0. First released: 2024-07-12.

## Highlights

- The request pipeline was rebuilt around events: request, validation, execution, response and errors are separate listeners you can hook into.
- `GET` on the API endpoint now returns an OpenRPC document, with `components/schemas` for response DTOs and `x-ufo-assertions` on parameters.
- The `ufo_json_rpc` config was simplified: two boolean switches for security, and the project info moved under `docs`.
- The service map is built by `ServiceMapFactory` and cached in `prod`.
- The facade layer (`RpcServerFacade`, `IFacadeRpcServer`) was removed.

## What's new

### Event-driven request lifecycle

`RpcServer` no longer runs procedures directly. It fires events through `RpcEventFactory`, and the bundle's own listeners in `Ufo\JsonRpcBundle\EventDrivenModel\Listeners` handle them. `ValidateParamsListener` (priority 1001/1000) validates params, `ServerEventListener` (priority -1000) runs the method and builds the response, `RpcErrorListener` turns kernel exceptions into JSON-RPC errors, and `LoggerListener` logs `rpc.error`. The event classes and the `RpcEvent` constants (`REQUEST`, `REQUEST_ASYNC`, `OUTPUT_ASYNC`, `PRE_EXECUTE`, `POST_EXECUTE`, `RESPONSE`, `ERROR`) live in `ufo-tech/rpc-objects` (`Ufo\RpcObject\Events`).

**How to use.** Register a standard Symfony listener. Pick a priority between the built-in ones to run after validation and before execution:

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Ufo\RpcObject\Events\RpcEvent;
use Ufo\RpcObject\Events\RpcPreExecuteEvent;

#[AsEventListener(RpcEvent::PRE_EXECUTE, 'onPreExecute', priority: 500)]
final class AuditListener
{
    public function onPreExecute(RpcPreExecuteEvent $event): void
    {
        // $event->rpcRequest, $event->service, $event->params
    }
}
```

### OpenRPC from the main endpoint

`ApiController::serverAction` handles `POST` as an RPC call and `GET` as `OpenRpcAdapter::adapt()`. The document now includes:
- `components/schemas` built from `#[RPC\Response]` DTO info
- `x-ufo-assertions` on each parameter, holding the source of its Symfony constraints
- the transports in `x-ufo.transport` on the server object
- links to the documentation

**How to use.**

```bash
curl https://example.test/api          # OpenRPC JSON (route prefix /api from install/routes)
```

Set `security.protected_doc: true` to require the token for this `GET` request too.

### Simplified configuration

```yaml
# config/packages/ufo_json_rpc.yaml
ufo_json_rpc:
    security:
        protected_api: true          # require token for POST (RPC calls)
        protected_doc: false         # require token for GET (documentation)
        token_name: 'Ufo-RPC-Token'  # request header name
        clients_tokens:
            - '%env(resolve:UFO_API_TOKEN)%'
    docs:
        project_name: 'My Project'
        project_description: ''
        project_version: null
        async_dsn_info: false
        validations:
            symfony_asserts: false
    async:
        # rpc_async: '%env(resolve:RPC_TRANSPORT_DSN)%'
        # failed: ...
```

## Changes and fixes

- New `ServiceMap` / `ServiceMapFactory`. The service map is cached in `prod` under the key `rps.service_map` with a TTL of one year. Other environments rebuild it on every request.
- `RpcCacheService` now works on `Psr\Cache\CacheItemPoolInterface` and no longer implements `CacheInterface`.
- `SecurityListener` checks the token on `kernel.request`. `RpcErrorListener` maps token errors to HTTP 401 (header missing) and 403 (invalid token).
- `IRpcSecurity` is aliased to `TokenRpcSecurity`. The async handler and the `ufo:rpc:process` command use `TokenRpcCliSecurity` directly.
- The bundle's `TypeHintResolver` was removed. The one from `Ufo\RpcObject\Helpers` is used instead.
- Requires `ufo-tech/rpc-objects >= 2.4.0`.

## Upgrade notes

- **Config keys changed.** The tree is declared with `ignoreExtraKeys(false)`, so old keys are **not rejected but are silently ignored**. Migrate them by hand:
  - `project_name`, `project_description`, `project_version` → `docs.*`
  - `security.protected_methods: ['POST', 'GET']` → `security.protected_api` / `security.protected_doc` (booleans; defaults are `true` / `false`)
  - `security.token_key_in_header` → `security.token_name`
  - `docs.response.async_dsn_info` → `docs.async_dsn_info`. `docs.response.validations.symfony_asserts` → `docs.validations.symfony_asserts`
  - `docs.response.key_for_methods` and `docs.response.validations.json_schema` were removed. The JSON Schema is always generated.
- **Routes removed:** `GET /method/{method}` and `GET /openrpc.json`. Use `GET` on the main endpoint, which now returns OpenRPC instead of the old service map array.
- **Classes removed:** `Server\RpcServerFacade`, `Server\RpcServerFacadeCli`, `Interfaces\IFacadeRpcServer`, `Server\ServiceMap\TypeHintResolver`.
- **Listeners moved:** `EventListener\HandleExceptionListener` → `EventDrivenModel\Listeners\RpcErrorListener`, and `EventListener\HandleCliListener` → `EventDrivenModel\Listeners\CliRpcListener`. Update any code that injected or decorated the facades.
- Clear the application cache after deploying to `prod` so the service map is rebuilt.
