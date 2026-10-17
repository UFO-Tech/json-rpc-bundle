[Українська](README-UA.md)

# 8.x

Major 8 rebuilds the request flow of the bundle around Symfony events: the RPC endpoint is handled by a `kernel.request` listener, `ufo:rpc:process` by a `console.command` listener, and the RPC lifecycle events (`rpc.pre_execute`, `rpc.post_response`, `rpc.error` …) now belong to the bundle. HTTP, CLI and async calls share one security model based on token holders, and parameter convertors turn scalar input into `BackedEnum`, `DateTime*` and UUID objects. Version 8.1 makes the OpenRPC documentation follow PHPDoc types (`ItemDTO[]`, `array<…>`, unions), hydrates DTO collections passed as parameters and publishes enum values in schemas.

## Requirements

| | 8.0.0 | 8.1.14 |
|---|---|---|
| PHP | >= 8.3 | >= 8.3 |
| Symfony | ^7.2 | ^7.2 |
| ufo-tech/dto-transformer | ^1.0 | ^1.1 |
| ufo-tech/rpc-objects | ^3.0 | ^3.1 |
| ufo-tech/rpc-exceptions | ^1.1 | ^1.3 |

New in 8.0: `symfony/yaml`, `symfony/uid`, `ramsey/uuid` ^4.

## Versions

| Version | Highlights |
|---|---|
| [8.0](8.0/README.md) | Event-driven HTTP/CLI flow; RPC events in the bundle incl. `rpc.post_response`; parameter convertors (enum, DateTime, UUID); unified token security for HTTP/CLI/async; per-service cache |
| [8.1](8.1/README.md) | OpenRPC schemas from PHPDoc; DTO collections as parameters; enum schemas for params, DTO properties, arrays and unions |

## Upgrading from 7.x

- Event classes moved from `Ufo\RpcObject\Events\*` to `Ufo\JsonRpcBundle\EventDrivenModel\Events\*`.
- POST requests are no longer handled by `ApiController` (it serves only `GET` docs); `RpcRequestHandler::handle()` has no `Request` argument.
- `IRpcSecurity` API replaced (`setTokenHolder()`, `getTokenHolder()`, `isValidApiRequest()`, `isValidDocRequest()`); `TokenRpcCliSecurity`, `Security\Helper`, `SecurityListener` removed.
- `RpcSecurityConfig::$tokenKeyInHeader` → `$tokenName`, `TOKEN_KEY` → `TOKEN_NAME` (YAML key `token_name` unchanged).
- `ufo:rpc:process`: `-t/--token` → `--<token_name in lower case>` (default `--ufo-rpc-token`).
- Removed: `CurrentRpcRequestHolder`, `RpcRequestHelper`, `CliRpcListener`, `DTOToParamsEventListener`, `ServiceMapFactory::getServiceLocator()`; `StopHandlerException` → `StopHandler`.
- Missing token → `RpcTokenNotSentException` (HTTP 401).
- Service map cache key changed — clear the cache after the upgrade.
- Details: [8.0 upgrade notes](8.0/README.md#upgrade-notes).
