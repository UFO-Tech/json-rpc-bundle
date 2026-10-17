[Українська](README-UA.md)

# 8.0 — Event-driven request flow, parameter convertors, unified HTTP/CLI security

Tags: 8.0.0, 8.0.1, 8.0.2, 8.0.3, 8.0.4, 8.0.5. First released: 2025-06-05.

## Highlights

- **Request handling moved into Symfony events**: POST to the RPC endpoint is handled by a `kernel.request` listener; `ApiController` serves only documentation.
- **RPC lifecycle events live in the bundle** (`Ufo\JsonRpcBundle\EventDrivenModel\Events\*`), including the new `rpc.post_response` that fires after the response is sent.
- **Parameter convertors**: scalar ↔ object conversion for `BackedEnum`, `DateTime*` and UUID (`symfony/uid`, `ramsey/uuid`), extensible through `IParamConvertor`.
- **One security model for HTTP, CLI and async** via token holders; `ufo:rpc:process` takes the token as `--<token_name>` and accepts batch requests.
- **Lazy, per-service cache** of the service map (`ServiceHolder`).

## What's new

### Event-driven HTTP and CLI flow

`SymfonyFlowListener` handles the RPC endpoint on `kernel.request` (POST → `RpcRequestHandler`), and handles `ufo:rpc:process` on `console.command`. The raw request is parsed by a request carrier (`RpcFromHttp`, `RpcFromCli`) held in `RequestCarrier`; both single and batch requests are supported in HTTP and CLI. `OPTIONS` and `X-Requested-With: XMLHttpRequest` requests to the endpoint get an empty `200` response. Errors are converted to JSON-RPC responses by `SymfonyErrorListener` (HTTP and console).

**How to use:** nothing to configure. CLI call:

```bash
php bin/console ufo:rpc:process '{"id":1,"method":"ping","params":{}}' --ufo-rpc-token=<token>
```

The token option name is `token_name` in lower case (default `Ufo-RPC-Token` → `--ufo-rpc-token`). It is required only when `security.protected_api: true`.

### RPC lifecycle events

Events and their names (constants of `RpcEvent`):

| Name | Class | Payload |
|---|---|---|
| `rpc.request` | `RpcRequestEvent` | `rpcRequest` |
| `rpc.async_request` | `RpcAsyncRequestEvent` | `rpcRequest` |
| `rpc.async_output` | `RpcAsyncOutputEvent` | `rpcRequest`, `batchRequest`, `output` |
| `rpc.pre_execute` | `RpcPreExecuteEvent` | `rpcRequest`, `service`, `params` (mutable) |
| `rpc.post_execute` | `RpcPostExecuteEvent` | `result`, `rpcRequest`, `service`, `params` |
| `rpc.pre_response` | `RpcPreResponseEvent` | `response`, `rpcRequest`, `service` |
| `rpc.post_response` | `RpcPostResponseEvent` | `response`, `rpcRequest`, `service` |
| `rpc.error` | `RpcErrorEvent` | `rpcRequest`, `exception`, `rpcError` (replaceable) |

`rpc.post_response` is fired on `kernel.terminate` / `console.terminate`, i.e. after the client got the response.

**How to use:**

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPostResponseEvent;

#[AsEventListener(RpcEvent::POST_RESPONSE)]
final class AuditListener
{
    public function __invoke(RpcPostResponseEvent $event): void
    {
        // $event->rpcRequest->getMethod(), $event->response ...
    }
}
```

### Parameter convertors

`ChainParamConvertor` collects services tagged `rpc.param_convertor` (autoconfigured for `IParamConvertor`). Built-in: `BackedEnumConvertor`, `DateTimeConverter` (`DateTime`, `DateTimeImmutable`, `DateTimeInterface`; output format `Y-m-d H:i:s`), `UuidConverter` (`Symfony\Component\Uid\*` and `Ramsey\Uuid\*`). Incoming values are converted for parameters marked with the `#[RPC\Param]` attribute (`ufo-tech/rpc-objects`); in a DTO result, properties whose real type is a class are converted back to scalars before the response. The constructor of `RPC\Param` belongs to `ufo-tech/rpc-objects` and is not documented in this bundle.

**How to use** — a custom convertor:

```php
use Ufo\JsonRpcBundle\ParamConvertors\IParamConvertor;

final class MoneyConvertor implements IParamConvertor
{
    public function toScalar(object $object, array $context = [], ?callable $callback = null): string|int|float|null
    {
        return $object->amount;
    }

    public function toObject(int|string|float|null $value, array $context = [], ?callable $callback = null): ?object
    {
        return new Money((int) $value);
    }

    public function supported(string $classFQCN): bool
    {
        return $classFQCN === Money::class;
    }
}
```

With autoconfigure on, the class is registered automatically.

### Unified security (token holders)

`IRpcSecurity` now works with an `IRpcTokenHolder`: `HttpTokenHolder` (header `token_name`), `CliTokenHolder` (option `--<token_name>`), `RpcAsyncTokenHolder` (`token` field of an async message). `TokenRpcSecurity` exposes `isValidApiRequest()` and `isValidDocRequest()`; documentation routes are checked by `DocSecurityListener` when `protected_doc: true`.

**How to use:** configuration keys are unchanged:

```yaml
ufo_json_rpc:
    security:
        protected_api: true
        protected_doc: false
        token_name: 'Ufo-RPC-Token'
        clients_tokens:
            - '%env(resolve:UFO_API_TOKEN)%'
```

### Per-service cache of the service map

Each procedure description is cached separately (`service_<method>` key) and loaded on demand by `ServiceHolder`; extra lookup sources can be added by implementing `IServiceHolder` (tag `rpc.service_holder`). The service map cache key changed from `rps.service_map` to `rpc.service_map`.

**How to use:** nothing to configure; run `php bin/console cache:clear` after the upgrade.

## Changes and fixes

- 8.0.0: Async processes: default timeout raised from 60 to 3600 s; errors from an async sub-request become an error response for that request instead of breaking the whole batch.
- 8.0.0: All config value objects have defaults (`RpcSecurityConfig`, `RpcDocsConfig`, `RpcAsyncConfig`), so missing keys no longer cause notices.
- 8.0.0: `ConstraintsImposedException` produces an `RpcError` with the list of violated constraints (`RpcErrorListener` on `rpc.error`).
- 8.0.1: DTO reflection limited to 3 levels of nesting (`DtoReflector::DEPTH_SIZE`) to avoid infinite recursion.
- 8.0.2: `fastcgi_finish_request()` is called before `rpc.post_response`, so post-response listeners do not delay the client (PHP-FPM).
- 8.0.3: OpenRPC: parameters typed as a DTO class get a full object schema; parameter validation listeners now run before parameter conversion; a non-nullable parameter is no longer marked optional because of a `null` default.
- 8.0.4: OpenRPC: `BackedEnum` parameters and properties get `enum` + `type` and UFO enum metadata (`UfoEnumsHelper`); `Choice` constraints with an enum callback also emit it; `x-ufo*` keys come from `XUfoValuesEnum`.
- 8.0.5: `RequestCarrier::getSpecialParams()` returns the request's special params (`getSpecialParams()`) instead of all params.

## Upgrade notes

From 7.6:

- Event classes moved from `Ufo\RpcObject\Events\*` to `Ufo\JsonRpcBundle\EventDrivenModel\Events\*`. Update `use` statements in your listeners.
- `ApiController::serverAction()` handles only `GET` (OpenRPC). POST is handled by `SymfonyFlowListener`; code that relied on `RpcRequestHandler::handle(Request $request)` must use `handle()` (the request comes from `RequestCarrier`). `ApiController::COLLECTION_ROUTE` and `OPEN_RPC_ROUTE` constants were removed.
- Security API changed: `IRpcSecurity` methods are now `setTokenHolder()`, `getTokenHolder()`, `isValidApiRequest()`, `isValidDocRequest()` (old `getTokenHeaderKey()`, `getToken()`, `isValidToken()`, `isValidRequest()` removed). `TokenRpcCliSecurity`, `Security\Helper`, `SecurityListener` were removed. Custom `IRpcSecurity` implementations must be rewritten.
- `RpcSecurityConfig`: property `tokenKeyInHeader` → `tokenName`, constant `TOKEN_KEY` → `TOKEN_NAME`; `protectedMethods` removed. The YAML key `token_name` is unchanged.
- `ufo:rpc:process`: option `-t/--token` replaced by `--<token_name in lower case>`; the `json` argument became optional.
- Removed: `CurrentRpcRequestHolder`, `RpcRequestHelper` (use `RequestCarrier`), `CliRpcListener`, `DTOToParamsEventListener` (replaced by `ParamsConverterEventListener`), `ServiceMapFactory::getServiceLocator()`. `Exceptions\StopHandlerException` renamed to `Exceptions\StopHandler`. `RpcServer` depends on `IServiceHolder` instead of `ServiceMap`.
- Token-missing error is `RpcTokenNotSentException` (HTTP 401) from `ufo-tech/rpc-exceptions` ^1.1.
- New requirements: `symfony/yaml`, `symfony/uid`, `ramsey/uuid` ^4, `ufo-tech/rpc-exceptions` ^1.1.
- Clear the cache: the service map cache key changed.
