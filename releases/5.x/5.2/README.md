[Українська](README-UA.md)

# 5.2 — Own service map, parameter validation and JSON Schema

Tags: 5.2.0. First released: 2024-05-02.

## Highlights

- Laminas dependency removed: the server, service map and method reflection are now implemented in the bundle (`ServiceLocator`, `Service`, `UfoReflectionProcedure`).
- Parameter validation with Symfony Validator constraints declared via `#[RPC\Assertions]`.
- JSON Schema (draft-07) for method parameters generated from those constraints and published in the API docs.
- Richer docs from PHP types and docblocks: descriptions, nullable/union types, defaults, optional flags.
- Procedure naming via `#[RPC\Info]` (alias and separator); new `GET <prefix>/method/{method}` endpoint for a single method.
- SoapUI export removed.

## What's new

### Parameter validation (`#[RPC\Assertions]`)

Method parameters can carry `Ufo\RpcObject\RPC\Assertions` with a list of Symfony constraints. Before the call, `Validations\RpcValidator` validates each parameter; on violations the client receives a JSON-RPC error (`ConstraintsImposedException`, a `RpcBadParamException`) whose `data` lists messages per parameter.

**How to use:**

```php
use Symfony\Component\Validator\Constraints as Assert;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

class UserProcedure implements IRpcService
{
    public function create(
        #[RPC\Assertions([new Assert\NotBlank(), new Assert\Email()])]
        string $email,
    ): int {
        // ...
    }
}
```

### JSON Schema in the docs

For each method the `GET` doc contains `json_schema` (draft-07, `type: object`, `properties`, `required`) built from parameter types and constraints. Constraints translated to schema keywords: `NotBlank`, `Length`, `Range`, `Email`, `EqualTo`, `Regex`.

**How to use:** add `#[RPC\Assertions]` as above, then `GET /api` or `GET /api/method/UserProcedure.create`.

### Docs from types and docblocks

The service map is built by reflection of public methods: summary of the docblock → method description, `@param` descriptions, native or `@return` types (including nullable and union types), default values and `optional`. `#[RPC\Response]` (from `ufo-tech/rpc-objects`) on a method fills `responseFormat` in the docs; its arguments are defined in `rpc-objects` and not documented in this bundle.

### Procedure naming (`#[RPC\Info]`)

`#[RPC\Info]` on a procedure class sets the alias used instead of the short class name and the separator between alias and method (default `.`). The built-in `PingProcedure` uses `#[RPC\Info("")]`, so its method is just `ping`.

**How to use:**

```php
#[RPC\Info('user')]
class UserProcedure implements IRpcService { /* user.create, ... */ }
```

### Single-method docs endpoint

`GET <prefix>/method/{method}` (route `ufo_rpc_api_collection`) returns the doc of one method; unknown names produce a "method not found" error. It is covered by token security in the same way as the main route.

## Changes and fixes

- `RpcServer` no longer extends `Laminas\Json\Server\Server`; service map envelope is `JSON-RPC-2.0/UFO-RPC-5`.
- PHP `TypeError` during a call is returned as `RpcBadParamException` with a readable message (`Parameter "x" ...`).
- Missing required named parameters produce `Required parameter "x" not passed`.
- Token check now matches protected routes with Symfony `UrlMatcher` (main route and `/method/{method}`) instead of comparing the raw URI.
- CLI: `ufo:rpc:process --token` value became optional; CLI security checks the token only when `POST` is in `protected_methods`.
- Uncaught-error response: fixed key `jsorpc` → `jsonrpc`; also handled for the `/method/{method}` route.
- Error log context: array `data` is serialized to YAML; works when the request object is missing.
- Known issue in 5.2.0: when validation fails, the error is returned to the client, but `RpcServer::dispatch()` still invokes the method (fixed in 6.x).
- Repository: `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`, issue templates added.

## Upgrade notes

- Requires Symfony `^6.4` (was `6.*`); new dependencies `phpdocumentor/reflection-docblock` and `symfony/expression-language`. `laminas/*` packages are no longer installed — add them yourself if your code used them.
- SoapUI export (`<prefix>/soapui.xml`) and the `SoupUi\*` classes are removed.
- The `GET` doc format changed (own `ServiceLocator::toArray()`; per-method keys `name`, `decription` (sic), `parameters`, `json_schema`, `returns`, `responseFormat`). Update clients that parse it.
- API changes for code extending the bundle: `IFacadeRpcServer::addProcedure()` lost the `$argv` argument, `getServiceMap()` returns `ServiceLocator|RpcResponse`, new `handleSmRequest()`; `RpcServer` and `RpcServerFacade` constructors changed; `DependencyInjection\Compiler\ApiProceduresPass` removed.
- Configuration keys are unchanged.
