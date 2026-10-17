[Українська](README-UA.md)

# 6.4 — Symfony 7, OpenRPC docs, response cache and Messenger transport

Tags: 6.4.0. First released: 2024-07-07.

## Highlights

- Symfony 7 / PHP 8.3 baseline.
- OpenRPC specification of the API at `GET <prefix>/openrpc.json`; the old `GET` doc format is marked deprecated.
- Per-method response caching with `#[RPC\Cache]`; the service map is cached in `prod`.
- Asynchronous requests through Symfony Messenger: the `async.rpc_async` DSN creates a transport and a handler for `RpcAsyncRequest`.
- New config: project metadata (`project_name`, `project_description`, `project_version`) and `docs` options; typed config objects in `ConfigService\*`.
- Validation moved to `ufo-tech/rpc-objects`; a method is no longer executed when its parameters fail validation.

## What's new

### OpenRPC documentation

`GET <prefix>/openrpc.json` (`OpenRpcAdapter`, built on `psx/openrpc`) returns an OpenRPC document: `info` from project metadata, a server with the API URL, and for every method its parameters (with JSON Schema from constraints), result type and `@return` description, and a tag with the procedure class. The legacy `GET <prefix>` doc now contains `WARNING` and `NEW_FORMAT` keys pointing to it.

**How to use:**

```yaml
# config/packages/ufo_json_rpc.yaml
ufo_json_rpc:
    project_name: 'Billing API'
    project_description: 'Invoices and payments'
    project_version: '1.2.0'
```

```console
$ curl https://example.com/api/openrpc.json
```

### Response cache (`#[RPC\Cache]`)

A method marked with `Ufo\RpcObject\RPC\Cache` has its result stored in the Symfony application cache (`CacheInterface`) under a key built from the method name and a SHA-256 hash of the parameters. Arguments: `lifetimeSecond` (default 60) and `environments` (default `['prod']`); in other environments the cache is not used.

**How to use:**

```php
use Ufo\RpcObject\RPC;

class RatesProcedure implements IRpcService
{
    #[RPC\Cache(lifetimeSecond: 600, environments: ['prod'])]
    public function getRates(string $currency): array { /* ... */ }
}
```

In `prod` the built service map is also cached (key `rps.service_locator`), so method reflection is not repeated on every request. Clear the application cache after deploying changed procedures.

### Async transport via Symfony Messenger

If `async.rpc_async` is set, the bundle prepends `framework.messenger` config: a transport `rpc_async` (and `failed`, if set) and routing of `Ufo\RpcObject\RpcAsyncRequest` to `rpc_async`. `Server\Async\RpcAsyncProcessor` is a `#[AsMessageHandler]` that executes the request (with the token from the message) and prints the request/response to the worker output. The bundle consumes these messages; producing them is up to the client side (not part of this bundle).

**How to use** (requires `symfony/messenger`, which is not in the bundle's `require`):

```yaml
ufo_json_rpc:
    async:
        rpc_async: '%env(resolve:RPC_TRANSPORT_DSN)%'
        # failed: '%env(resolve:RPC_FAILED_DSN)%'
```

```console
$ php bin/console messenger:consume rpc_async
```

### Documentation options (`docs.response`)

- `key_for_methods` — key for the method list in the `GET` doc (template value `methods`).
- `async_dsn_info` — add `transport.async` (parsed from the `rpc_async` DSN) to the `GET` doc.
- `validations.symfony_asserts` — add `symfony_assertions` per method.
- `validations.json_schema` — add `json_schema` per method (default `true`; marked deprecated, "will be removed in 7.0").

**How to use:**

```yaml
ufo_json_rpc:
    docs:
        response:
            key_for_methods: methods
            async_dsn_info: false
            validations:
                symfony_asserts: true
```

### Typed configuration services

`ConfigService\RpcMainConfig` (with `RpcSecurityConfig`, `RpcDocsConfig`, `RpcAsyncConfig`) is a service holding the normalized config, the environment and the current request URL; it can be injected into your code.

## Changes and fixes

- Validation is performed by `Ufo\RpcObject\Rules\Validator\RpcValidator` (from `ufo-tech/rpc-objects`); the method call happens only after successful validation.
- The `GET` doc: `transport` is now an object (`sync` URL/method, optional `async`), envelope is `JSON-RPC-2.0/UFO-RPC-<bundle version>`, project `name`/`description` added, a `readDocs` block with links, the per-method key `decription` renamed to `description`, optional `throws`.
- Uncaught exceptions on API routes: the response uses the request `id` (one error per item for batches) and HTTP 401 for `RpcTokenNotFoundInHeaderException`, 403 for `RpcInvalidTokenException`, 200 otherwise.
- `@return` description is included in method docs.
- Async batch processes locate `bin/console` correctly when started from the CLI; empty tokens are not passed to `ufo:rpc:process`.
- Callback delivery sends the response serialized as JSON.
- Serialization context for responses unified in `Serializer\RpcResponseContextBuilder`.
- Known issue in 6.4.0: `ApiController::OPEN_RPC_ROUTE` and `COLLECTION_ROUTE` both equal `ufo_rpc_api_collection`; with two routes of the same name Symfony keeps only the last one registered, so `GET <prefix>/method/{method}` may be unreachable. Check `bin/console debug:router` in your app.

## Upgrade notes

- Requirements: PHP `>=8.3`, Symfony `^7.0`, `ufo-tech/rpc-objects >=2.3.1`, `ufo-tech/rpc-exceptions >=1.0.3`, new `psx/openrpc v0.1.1`.
- Removed: `Interfaces\IRpcValidator` and `Validations\RpcValidator` (use the validator from `rpc-objects`).
- `ITokenValidator::isValid()` now has return type `true` (throw `RpcInvalidTokenException` on failure); `SimpleTokenValidator`, `TokenRpcSecurity`, `TokenRpcCliSecurity` take `RpcMainConfig` instead of scalar arguments. Update custom implementations and service definitions.
- Defaults: `RpcMainConfig` fills keys missing from your config with values from the bundle's `install/packages/ufo_json_rpc.yaml` template. Per the code, if you omit the `security` section, the template's `protected_methods: ['POST']` and example tokens apply. Always define `security` explicitly.
- Clients parsing the legacy `GET` doc must adapt to the new structure (see above) or switch to `openrpc.json`.
