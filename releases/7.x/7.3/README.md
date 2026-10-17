[Українська](README-UA.md)

# 7.3 — Postman export, richer JSON Schema, async hardening

Tags: 7.3.10 (the only tagged 7.3 release; 7.3.0–7.3.9 were version bumps in `composer.json` without git tags). First released: 2025-01-30.

## Highlights

- New `GET /postman` endpoint that exports a Postman Collection v2.1 of all RPC methods.
- JSON Schema generation covers more Symfony constraints: `All`, `Choice` (including enum callbacks), `Collection`, `Date`, `GreaterThan`, `GreaterThanOrEqual`, `NotNull`, `Optional`, `Type` and `Uuid`. Nested constraints are supported.
- Security fix: messages processed by the async (Messenger) handler now pass token validation.
- A child procedure class can override a parent's RPC methods without a "service already registered" error.
- A PHPUnit test suite and Docker dev tooling were added.

## What's new

### Postman collection export

`ApiController::postmanAction` (route `ufo_rpc_api_postman`, `GET /postman` relative to the bundle route prefix) returns a collection built by `PostmanAdapter`. Methods are grouped into folders named after the procedure class's short name. Every request carries the headers `Content-Type`, `<security.token_name>: {{apiToken}}` and `AccessToken: {{accessToken}}`, and the collection defines the variables `apiToken` and `accessToken`.

**How to use**

```bash
curl https://example.test/api/postman > collection.json   # import into Postman
```

### More constraints in the generated JSON Schema

Constraints declared through `#[RPC\Assertions([...])]` are translated into JSON Schema keywords in the OpenRPC parameter schema. Examples: `Uuid` → `format: uuid` plus a length, `Date` → `format: date`, `Choice` → `enum`. The `Choice` callback form `[EnumClass::class, 'method']` is resolved to its values. `All` → `items`, `Collection` → `properties`, and `Optional` merges its inner rules. Unsupported constraints are skipped with a warning to the PSR logger.

**How to use**

```php
use Symfony\Component\Validator\Constraints as Assert;
use Ufo\RpcObject\RPC;

public function find(
    #[RPC\Assertions([new Assert\Uuid()])]
    string $id,
    #[RPC\Assertions([new Assert\All([new Assert\Choice(['new', 'paid'])])])]
    array $statuses = [],
): array { /* ... */ }
```

### Overriding procedures by inheritance

`ServiceMap::addService()` now accepts a method name that is already registered when the new procedure class is a subclass of the registered one: the child replaces the parent. If the parent is added after the child, the parent is skipped. Any other duplicate still throws.

## Changes and fixes

- **Security:** `RpcAsyncProcessor::__invoke()` now calls `isValidRequest()` after setting the message token. Async RPC messages with a missing or invalid token are rejected when `protected_api` is enabled.
- Async: callbacks (`params.$rpc.callback`) are now sent from the Messenger handler. Callback requests send `Content-Type: application/json`. Worker output is timestamped and keeps Unicode unescaped.
- Fixed a standalone `null` return type being listed as `null, null`.
- Fixed type resolution for union-typed parameters in the JSON Schema normalizer.
- `TaggedIterator` was replaced with `AutowireIterator` (Symfony deprecation).
- `ufo-tech/rpc-objects` is pinned to `2.7.*`.

## Upgrade notes

- If you run async (Messenger) transports with `protected_api: true`, make sure producers put a valid client token on each message. Tokenless messages that used to run are now rejected.
- `ufo-tech/rpc-objects` is constrained to `2.7.*`.
