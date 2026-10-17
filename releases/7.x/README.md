[Українська](README-UA.md)

# ufo-tech/json-rpc-bundle 7.x

Major 7 rebuilt the bundle around an event-driven request pipeline. Validation, execution, locking, caching and error handling are now Symfony event listeners you can extend. The facade layer was dropped, and `GET` on the API endpoint serves a native OpenRPC document. Over the 7.x line the bundle added:
- DTO-based result schemas (`#[RPC\ResultAsDTO]`)
- hiding methods with `#[RPC\IgnoreApi]`
- a Postman collection export and broader Symfony-constraint → JSON Schema mapping
- method locks (`#[RPC\Lock]`) and automatic DTO hydration of parameters
- pluggable service-metadata fillers
- related-cache invalidation and warm-up (`#[RPC\CacheRelation]`) with new pre/post-response events

## Requirements

| | 7.0.0 | 7.6.0 |
|---|---|---|
| PHP | `>=8.3` | `>=8.3` |
| Symfony (`framework-bundle` and other `symfony/*`) | `^7.0` | `^7.2` |
| `ufo-tech/rpc-objects` | `>=2.4.0` | `^3.0` |
| Other | `ext-dom`, `psx/openrpc v0.1.1` | adds `ext-sysvsem`, `composer-runtime-api`, `symfony/lock ^7.2`, `ufo-tech/dto-transformer ^1.0` |

## Versions

| Version | Highlights |
|---|---|
| [7.0](7.0/README.md) | Event-driven core; OpenRPC on `GET` of the endpoint; simplified config (`protected_api` / `protected_doc`, `docs.project_*`) |
| [7.1](7.1/README.md) | `#[RPC\ResultAsDTO]` with nested DTO / collection schemas; environment info in OpenRPC |
| [7.2](7.2/README.md) | `#[RPC\IgnoreApi]` to hide public methods |
| [7.3](7.3/README.md) | Postman collection export (`GET /postman`); more constraints in JSON Schema; async token validation fix; child procedures override parents |
| [7.4](7.4/README.md) | `#[RPC\Lock]`; DTO parameters hydrated automatically; tagged service fillers; `CurrentRpcRequestHolder` |
| [7.5](7.5/README.md) | Symfony 7.2 baseline; CLI token check and async batch fixes; deprecation cleanup |
| [7.6](7.6/README.md) | `#[RPC\CacheRelation]` invalidation and warm-up; `rpc.pre_response` / `rpc.post_response`; generic attribute collection; rpc-objects 3 + dto-transformer |

## Upgrading from 6.x

1. **Rewrite `config/packages/ufo_json_rpc.yaml`.** Unknown keys are silently ignored, not rejected.
   - `project_name`, `project_description`, `project_version` → `docs.project_*`
   - `security.protected_methods` → `security.protected_api` (POST) / `security.protected_doc` (GET), both booleans
   - `security.token_key_in_header` → `security.token_name`
   - `docs.response.*` flattened into `docs.*`. `key_for_methods` and `validations.json_schema` were removed.
2. **Routes:** `/method/{method}` and `/openrpc.json` were removed. `GET` on the API endpoint returns OpenRPC instead of the old service map.
3. **Removed classes:** `RpcServerFacade`, `RpcServerFacadeCli`, `IFacadeRpcServer`, `Server\ServiceMap\TypeHintResolver`. The `EventListener\*` classes moved to `EventDrivenModel\Listeners\*`. Since 7.1, `Serializer\RpcErrorNormalizer` and `Serializer\RpcResponseContextBuilder` live in `Ufo\RpcObject\Transformer`.
4. **Dependencies:** `ufo-tech/rpc-objects` 2.4+ for 7.0 and `^3.0` for 7.6. From 7.4, `ext-sysvsem` and `symfony/lock` are required. From 7.5.1, Symfony 7.2+.
5. **Behaviour:** since 7.3, async (Messenger) messages are token-checked when `protected_api` is on. Since 7.6, `#[RPC\Response]` and `RpcEvent::RESPONSE` are no longer used (see [7.6](7.6/README.md)).
6. Clear the application cache after each upgrade. The service map is cached in `prod` (`rps.service_map`), and its format changed several times during 7.x.
