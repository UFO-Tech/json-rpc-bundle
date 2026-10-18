[Українська](README-UA.md)

# Release notes

What was added in every version and how to use it. Each minor version has its own page; each major has an overview with upgrade notes: [10.x](10.x/README.md) · [9.x](9.x/README.md) · [8.x](8.x/README.md) · [7.x](7.x/README.md) · [6.x](6.x/README.md) · [5.x](5.x/README.md) · [4.x](4.x/README.md).

| Version | Released | Highlights |
| --- | --- | --- |
| [10.4](10.x/10.4/README.md) | 2026-10-18 | Method titles and descriptions per API group (`translations/rpc/<group>.<locale>.yaml`), translations from extensions (`translations.paths`), `RpcLocaleNegotiator` service |
| [10.3](10.x/10.3/README.md) | 2026-10-18 | 10.3.1: XHR calls, `#[Autowire]` in method arguments, `concat`, `deprecated` in OpenRPC fixed. Batch modes (inline / limited processes), JSON-RPC notification rules in batch, docs cache with `ETag`, translated method titles, response cache keyed by implementation, 2.4–4.2× throughput |
| [10.2](10.x/10.2/README.md) | 2026-10-15 | API groups: several entry points (public, internal, BFF) with own methods, versions, docs; token validation per group (`security.providers`) |
| [10.1](10.x/10.1/README.md) | 2026-09-25 | Symfony 8 support, JSON-RPC notifications (`204`, run after the response), strict JSON-RPC 2.0 checks with per-parameter errors |
| [10.0](10.x/10.0/README.md) | 2026-02-26 | API versions side by side (`/rpc/v2`, `removedMethods`), cleaner OpenRPC/Postman, automatic parameter conversion incl. Doctrine entities |
| [9.2](9.x/9.2/README.md) | 2026-01-13 | Non-blocking `$rpc.callback` (result posted after the response), `GET /api?info` without methods |
| [9.1](9.x/9.1/README.md) | 2025-11-17 | Several async transports as a typed list, separate response cache pool, `$rpc.ignoreCache`, deprecated methods marked in OpenRPC |
| [9.0](9.x/9.0/README.md) | 2025-10-01 | Precise OpenRPC schemas (unions, nullable, `Dto[]`, `array<string, Dto>`), enums end to end, DTO defaults and required properties |
| [8.1](8.x/8.1/README.md) | 2025-07-02 | OpenRPC schemas from PHPDoc types, `@param ItemDTO[]` turned into DTO arrays, enum values in docs |
| [8.0](8.x/8.0/README.md) | 2025-06-05 | Event-driven request flow with `rpc.post_response`, automatic conversion of enums / dates / UUIDs, one token model for HTTP, CLI and async |
| [7.6](7.x/7.6/README.md) | 2025-05-15 | `#[RPC\CacheRelation]` invalidates and warms related cache, `rpc.pre_response` / `rpc.post_response` events |
| [7.5](7.x/7.5/README.md) | 2025-04-18 | Symfony 7.2 baseline, fixes for the CLI token check and async batch results |
| [7.4](7.x/7.4/README.md) | 2025-03-14 | `#[RPC\Lock]` against concurrent calls, class-typed parameters hydrated into DTOs, pluggable service-metadata fillers |
| [7.3](7.x/7.3/README.md) | 2025-01-30 | Postman Collection export (`GET /postman`), Symfony constraints as JSON Schema, child classes override parent RPC methods, token check for async |
| [7.2](7.x/7.2/README.md) | 2024-07-24 | `#[RPC\IgnoreApi]` keeps a public method out of the API and docs |
| [7.1](7.x/7.1/README.md) | 2024-07-24 | `#[RPC\ResultAsDTO]` result schemas from DTO classes, environment and package versions in the OpenRPC server entry |
| [7.0](7.x/7.0/README.md) | 2024-07-12 | Event-driven core (`RpcEvent::PRE_EXECUTE`, `ERROR`, …), full OpenRPC document with DTO schemas, simpler security config |
| [6.4](6.x/6.4/README.md) | 2024-07-07 | OpenRPC description at `/openrpc.json`, `#[RPC\Cache]` per parameters, async processing through Symfony Messenger |
| [5.2](5.x/5.2/README.md) | 2024-05-02 | Parameter validation with `#[RPC\Assertions]`, JSON Schema per method, own service map without Laminas |
| [4.5](4.x/4.5/README.md) | 2023-02-17 | Initial public baseline: `IRpcService`, batch with `@FROM` dependencies and parallel processes, `$rpc.callback`, token security |
