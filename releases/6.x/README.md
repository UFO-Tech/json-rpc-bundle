[Українська](README-UA.md)

# 6.x — Symfony 7, OpenRPC, caching and async transport

Requirements (composer.json at 6.4.0): PHP `>=8.3`, Symfony `^7.0`, `ufo-tech/rpc-objects >=2.3.1`, `ufo-tech/rpc-exceptions >=1.0.3`, `psx/openrpc v0.1.1`, `phpdocumentor/reflection-docblock >=5.3`. `symfony/messenger` is needed for the async transport but is not required by the bundle.

Major 6 moves the bundle to Symfony 7 and PHP 8.3. The API is described in the OpenRPC format (`openrpc.json`), method results can be cached with `#[RPC\Cache]`, and requests can be processed asynchronously through a Symfony Messenger transport configured by a single DSN. Configuration gains project metadata and documentation options, exposed as typed `ConfigService` objects; parameter validation moves to `ufo-tech/rpc-objects`.

| Version | Highlights |
|---|---|
| [6.4](6.4/README.md) | OpenRPC at `/openrpc.json`; `#[RPC\Cache]` response cache and cached service map; Messenger transport via `async.rpc_async`; `project_*` and `docs.*` config; 401/403 for token errors |

## Upgrading from the previous major

- PHP `>=8.3` and Symfony `^7.0` are required; update `ufo-tech/rpc-objects` to `>=2.3.1`.
- `Validations\RpcValidator` and `Interfaces\IRpcValidator` are removed (validator now in `rpc-objects`).
- `ITokenValidator::isValid()` returns `true`; security classes take `RpcMainConfig`.
- Missing config keys are filled from the bundle's config template, so define `security` explicitly.
- The legacy `GET` doc changed again and is deprecated; prefer `openrpc.json`.

Details: [6.4 upgrade notes](6.4/README.md#upgrade-notes).
