[Українська](README-UA.md)

# 5.x — Own service map, validation and JSON Schema

Requirements (composer.json at 5.2.0): PHP `>=8.1`, Symfony `^6.4`, `ufo-tech/rpc-objects 1.*`, `ufo-tech/rpc-exceptions 1.*`, `phpdocumentor/reflection-docblock ^5.3`, `symfony/expression-language ^6.3`.

Major 5 drops Laminas and replaces it with the bundle's own reflection-based service map. Method docs are generated from PHP types and docblocks, parameters can be validated with Symfony constraints via `#[RPC\Assertions]`, and the same constraints produce a JSON Schema for each method. Procedures can be renamed with `#[RPC\Info]`, and a single method's doc is available at `GET <prefix>/method/{method}`. The SoapUI export is removed.

| Version | Highlights |
|---|---|
| [5.2](5.2/README.md) | Laminas removed; `#[RPC\Assertions]` validation with per-parameter errors; JSON Schema in docs; docblock-based descriptions; `#[RPC\Info]` aliases; `/method/{method}` endpoint |

## Upgrading from the previous major

- Symfony `^6.4` is required; `laminas/*` packages are no longer pulled in.
- `<prefix>/soapui.xml` and `SoupUi\*` classes are removed.
- The `GET` doc format changed; clients parsing it must be updated.
- Extension points changed: `IFacadeRpcServer::addProcedure()` has no `$argv`, `getServiceMap()` returns `ServiceLocator`, `ApiProceduresPass` is removed, `RpcServer` no longer extends Laminas `Server`.
- Configuration keys (`security.*`) are unchanged.

Details: [5.2 upgrade notes](5.2/README.md#upgrade-notes).
