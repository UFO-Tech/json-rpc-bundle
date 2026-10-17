[Українська](README-UA.md)

# 4.5 — JSON-RPC 2.0 server for Symfony 6 (baseline)

Tags: 4.5.2. First released: 2023-02-17.

This is the earliest tagged release in the repository and serves as the baseline: it describes what the bundle offers as of 4.5.2. The project history goes back to 2017 (versions 1–3), but those versions are not tagged here.

## Highlights

- JSON-RPC 2.0 endpoint (`GET`/`POST` on one route) for Symfony 6 on PHP 8.1+.
- Any service implementing `IRpcService` becomes an RPC procedure; its public methods are exposed as `ClassName.method`.
- Batch requests with result chaining between calls (`@FROM:<id>(<field>)`).
- Parallel processing of batch requests in separate `ufo:rpc:process` console processes.
- Response delivery to a callback URL via the `$rpc.callback` parameter.
- Token-based protection of `GET`/`POST` and SoapUI project export.

## What's new

### RPC endpoint and service map

`ApiController` serves a single route `ufo_rpc_api_server`. `POST` handles JSON-RPC requests; `GET` returns an SMD-style service map (built on `laminas/laminas-json-server`) listing all methods, their parameters and return types. A built-in `ping` method returns `"PONG"`.

**How to use** — register the route (Symfony Flex recipe from `ufo-tech/recipes` does this automatically):

```yaml
# config/routes/ufo_json_rpc.yaml
ufo_json_rpc:
    resource: ../../vendor/ufo-tech/json-rpc-bundle/config/router.yaml
    prefix: /api
    trailing_slash_on_root: false
```

```json
{"id": 1, "method": "ping"}
```

### Procedures via `IRpcService`

`Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService` carries `#[AutoconfigureTag('ufo.rpc.service')]`; every tagged service is registered in the RPC server. Methods are named `<ShortClassName>.<method>`; parameters can be passed by name or by position.

**How to use:**

```php
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;

class MyRpcProcedure implements IRpcService
{
    public function sayHelloName(string $name): string
    {
        return 'Hello, ' . $name;
    }
}
```

```json
{"id": 1, "method": "MyRpcProcedure.sayHelloName", "params": {"name": "Mr. Anderson"}}
```

### Batch requests with result chaining

A request body starting with `[` is processed as a batch. A parameter value `@FROM:<requestId>(<field>)` is replaced with a field from the result of another request in the same batch; dependent requests run after their source.

**How to use:**

```json
[
  {"id": "create", "method": "SomeEntityProcedure.create", "params": {"name": "test"}},
  {"id": "activate", "method": "SomeEntityProcedure.changeStatus",
   "params": {"id": "@FROM:create(id)", "status": 1}}
]
```

### Parallel batch processing and `ufo:rpc:process`

Independent requests of a batch are started as separate processes of the `ufo:rpc:process` console command (`RpcAsyncProcessor`), so the response time is close to that of the slowest request. The per-request process timeout is 60 s by default and can be changed with the `$rpc.timeout` parameter. The command can also be called directly.

**How to use:**

```console
$ php bin/console ufo:rpc:process '{"id":1,"method":"ping"}' -t ClientTokenExample
```

Note: the processor starts `../bin/console`, i.e. it assumes the web process runs from `public/`.

### Callback delivery (`$rpc.callback`)

If a request contains `$rpc.callback`, the bundle POSTs the method response to that URL (`RpcCallbackProcessor`, Symfony HttpClient) and returns a `callback` status object instead of the result.

**How to use:**

```json
{"id": 1, "method": "SomeEntityProcedure.create",
 "params": {"name": "test", "$rpc.callback": "https://example.com/endpoint"}}
```

### Token security

Disabled by default. When enabled, requests with an HTTP method listed in `protected_methods` must send a token from `clients_tokens` in the configured header. `ufo:rpc:process` requires `-t/--token` when `POST` is protected. Custom validation is possible by implementing `Security\Interfaces\ITokenValidator`.

**How to use:**

```yaml
# config/packages/ufo_json_rpc.yaml
ufo_json_rpc:
    security:
        protected_methods: ['GET', 'POST']
        token_key_in_header: 'Ufo-RPC-Token'   # default
        clients_tokens:
            - '%env(resolve:UFO_API_TOKEN)%'
```

### SoapUI export

`GET <prefix>/soapui.xml` returns a SoapUI project with all methods, importable via *File → Import Remote Project*. Query parameters: `token` (substituted into project parameters) and `show_examples` (`1` — example values, default; `0` — types).

## Changes and fixes

- Errors are returned as JSON-RPC error objects; exceptions come from the external package `ufo-tech/rpc-exceptions`, request/response objects from `ufo-tech/rpc-objects`.
- Uncaught exceptions on the API route are converted to a JSON-RPC error response with HTTP 200 (`HandleExceptionListener`).
- Errors are logged through the PSR-3 logger, if available.
- Symfony Flex recipe (`ufo-tech/recipes`) installs default config and route files.

## Upgrade notes

Initial public baseline. Coming from 3.x: the bundle was reworked for PHP 8.1 and Symfony 6 (release 4.0, January 2023); procedures are now registered by implementing `IRpcService` (autoconfigured tag `ufo.rpc.service`). A detailed 3.x → 4.x migration guide is not documented in the code.
