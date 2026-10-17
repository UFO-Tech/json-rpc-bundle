[Українська](README-UA.md)

# 9.2 — Non-blocking callback requests, lightweight docs info

Tags: 9.2.0, 9.2.1. First released: 2026-01-13.

## Highlights

- Requests with `$rpc.callback` no longer block the client: the bundle answers immediately and runs the method after the HTTP response is sent (9.2.0).
- Callback delivery forwards the incoming HTTP request headers to the callback URL (9.2.0).
- `rpc.post_response` listeners now also run for console requests (`ufo:rpc:process`), because `fastcgi_finish_request()` is called only for HTTP carriers (9.2.0).
- `GET <api>?info` returns only the OpenRPC header (info, servers) without methods and components (9.2.1).

## What's new

### Non-blocking callback requests (9.2.0)

Before 9.2 a request with `$rpc.callback` was executed synchronously, the result was POSTed to the callback, and the client received the callback delivery status. Now `RpcRequestHandler` immediately returns a short acknowledgement and fires `rpc.pre_response`. The actual work happens in `AsyncServerEventListener::processAsync()` on `rpc.post_response`: it calls `RpcAsyncProcessor::processAsync()`, which executes the method and sends the result to the callback via `RpcCallbackProcessor`. Under PHP-FPM this runs after `fastcgi_finish_request()`, i.e. after the client has its response.

**How to use:**

```json
{
  "id": "order-42",
  "method": "Orders.export",
  "params": {"id": 42, "$rpc": {"callback": "https://client.example/rpc-callback"}}
}
```

Immediate response:

```json
{"id": "order-42", "result": {"async": true, "callback": "https://client.example/rpc-callback"}, "jsonrpc": "2.0"}
```

The full JSON-RPC response is then POSTed to `https://client.example/rpc-callback`.

### Header forwarding to the callback (9.2.0)

`RpcCallbackProcessor` copies all headers of the incoming HTTP request (when the carrier exposes `getHttpRequest()`) into the callback request; `Content-Type` and `Accept` are always set to `application/json`. This lets the callback endpoint authenticate the call with the same headers the client sent. Be aware that this includes the RPC token header (`Ufo-RPC-Token` by default) and any other credentials.

### Lightweight docs info (9.2.1)

`OpenRpcAdapter::adapt(bool $fullInfo = true)` can skip building methods and components. `ApiController::serverAction()` passes `false` when the `info` query parameter is present.

**How to use:**

```bash
curl 'https://example.com/api?info'
```

## Changes and fixes

- 9.2.0 — `RpcServer::$serviceHolder` is now `readonly public`. `RpcCallbackProcessor` depends on `RequestCarrier`; `AsyncServerEventListener` depends on `RpcAsyncProcessor`.
- 9.2.1 — `GET ?info` support. `OpenRpcAdapter` no longer passes the deprecated flag to `OpenRpcSpecBuilder::buildMethod()`, so in 9.2.1 methods are not marked `deprecated` in OpenRPC even if they carry `@deprecated` / `#[\Deprecated]` (the flag is still collected in `Service::isDeprecated()`). `composer.json` lists a second author.

## Upgrade notes

- Clients that send `$rpc.callback` and parse the immediate response must handle the new shape `{"async": true, "callback": "<url>"}`. The old `{"callback": {"url", "status", "data"}}` result is gone, and callback delivery errors are no longer reported in the immediate response.
- Callback endpoints now receive the caller's request headers, including the RPC token. Make sure callback URLs point to trusted hosts.
- For HTTP requests the post-response stage (and therefore the callback work) still starts with `fastcgi_finish_request()`. Under SAPIs without that function (e.g. mod_php, the PHP built-in server) the call throws, the error is swallowed and the method is not executed. Run the API under PHP-FPM if you rely on `$rpc.callback`.
