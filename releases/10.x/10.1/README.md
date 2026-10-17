[Українська](README-UA.md)

# 10.1 — Symfony 8, JSON-RPC notifications, stricter protocol

Tags: 10.1.0. First released: 2026-09-25.

## Highlights

- **Symfony 8 support** (`^7.2|^8.0`); PHP >= 8.4 is now required.
- **JSON-RPC notifications**: a request without `id` gets no response body (HTTP `204`) and is executed after the response is sent.
- **Stricter JSON-RPC 2.0**: unsupported `jsonrpc` version and empty batch are rejected with `-32600`.
- **Unified parameter errors**: missing, mistyped and invalid parameters return one error with per-parameter messages.

## What's new

### Symfony 8

All `symfony/*` requirements are `^7.2|^8.0`. `symfony/messenger` became an explicit requirement.

### JSON-RPC notifications

A request without `id` is a notification (`Ufo\RpcObject\RpcNotificationRequest`, from `ufo-tech/rpc-objects` ^3.8). The server does not return a response for it: a single notification over HTTP answers `204 No Content` with an empty body. The method runs on `RpcEvent::POST_RESPONSE`, i.e. after the response was sent (after `fastcgi_finish_request()` under PHP-FPM).

**How to use**

```bash
curl -i -X POST https://example.com/rpc \
  -H 'Content-Type: application/json' -H 'Ufo-RPC-Token: <token>' \
  -d '{"jsonrpc": "2.0", "method": "audit.log", "params": {"event": "login"}}'
# HTTP/1.1 204 No Content
```

The method signature does not change; its return value is discarded.

### Protocol checks

- `jsonrpc` other than `"2.0"` → `RpcBadRequestException` (`-32600`, "Unsupported jsonrpc protocol version").
- Empty batch `[]` → a single error response (`-32600`, "Can`t process empty batch request").

### Unified parameter errors

Missing required parameters, PHP `TypeError` on a parameter and failed DTO conversion now all produce `ConstraintsImposedException` with the message `Invalid Data for call method: <method>` and the problems per parameter in `error.data`. Class namespaces are stripped from messages. Previously a missing parameter threw a plain `RpcBadParamException` on the first one only.

```json
{
  "jsonrpc": "2.0", "id": 1,
  "error": {
    "code": -32602,
    "message": "Invalid Data for call method: user.create",
    "data": {"email": "Required parameter \"email\" not passed"}
  }
}
```

## Changes and fixes

- Errors inside DTO collections are attached to the right item (`errors[param][key]`).
- Removed the unused `DTOTransformerInitListener`.
- Tests moved to PHPUnit ^11.5 with a functional test kernel and protocol compliance tests.

## Upgrade notes

- PHP >= 8.4 (was >= 8.3).
- `ufo-tech/rpc-exceptions` ^2 (was ^1.3), `ufo-tech/rpc-objects` ^3.8, `ufo-tech/dto-transformer` ^3.1.
- Clients that sent requests without `id` and expected a result must send an `id`.
- Error handling that matched `RpcBadParamException` messages for missing/mistyped parameters should read `error.data` instead.
- Notification execution writes the request and response to stdout via `echo` (in 10.1 and 10.2); replaced by logging in [10.3](../10.3/README.md).
