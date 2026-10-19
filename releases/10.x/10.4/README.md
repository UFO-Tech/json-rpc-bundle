[Українська](README-UA.md)

# 10.4 — Translations per API group, translations from extensions, locale negotiator

Tags: 10.4.0, 10.4.1, 10.4.2, 10.4.3, 10.4.4. First released: 2026-10-18.

## Highlights

- **Method titles and descriptions per API group**: one method can be documented differently in `/rpc` and `/rpc/bfa`.
- **Translations from extensions**: a bundle that adds RPC methods can ship their translations; the application can override them.
- **`RpcLocaleNegotiator`**: the `Accept-Language` logic of the documentation as a public service for extensions.

## What's new

### Translations per API group

The base translation stays where it was (`translations/rpc.<locale>.yaml`). Overrides for one API group go to the `rpc/` subdirectory, named `<group>.<locale>.yaml` (or `.yml`):

```
translations/rpc.uk.yaml        # base, for all groups
translations/rpc/bfa.uk.yaml    # only for the bfa group
translations/rpc/bfa.en.yaml
```

```yaml
# translations/rpc/bfa.uk.yaml
catalog.list:
    title: Каталог для партнерів    # description is taken from the base translation
```

An override may contain only `title` or only `description`; the other field is looked up further. Every field is looked up in this order (the group wins over the language):

| Step | Source |
| --- | --- |
| 1 | group, client locale |
| 2 | group, `kernel.default_locale` |
| 3 | base, client locale |
| 4 | base, `kernel.default_locale` |
| 5 | `#[RPC\Info]` / PHPDoc |

The locale is the last segment of the file name and the group is everything before it, so `rpc/partner.v2.en.yaml` is the group `partner.v2`. Everything is resolved at container compile time; in debug the container is rebuilt when files in `translations/` or `translations/rpc/` change.

> **Do not name a group like a Symfony Translator domain** (`messages`, `validators`, `security` or your own). When the Translator is enabled it scans `translations/` recursively and would treat `rpc/messages.uk.yaml` as part of the `messages` domain.

### Translations from extensions

```yaml
ufo_json_rpc:
  translations:
    paths:
      - '%kernel.project_dir%/vendor/acme/rpc-extension/translations'
```

Each directory has the same layout: `rpc.<locale>.yaml` and `rpc/<group>.<locale>.yaml`. A bundle adds its directory itself in `prepend()`:

```php
public function prepend(ContainerBuilder $container): void
{
    $container->prependExtensionConfig('ufo_json_rpc', [
        'translations' => ['paths' => [__DIR__.'/../../translations']],
    ]);
}
```

Directories are merged field by field; a later one overrides an earlier one, and the application's `translations/` always goes last.

### `RpcLocaleNegotiator`

`Ufo\JsonRpcBundle\Server\RpcLocaleNegotiator` picks the language from `Accept-Language` the same way the documentation does (`uk-UA` matches `uk`; without a match or header — the first locale, the default one if it has translations). By default it chooses among the locales with RPC translations; an extension with its own translations passes its locales:

```php
public function __construct(private RpcLocaleNegotiator $localeNegotiator) {}

$locale = $this->localeNegotiator->negotiate($request, ['en', 'uk']); // null when the list is empty
$this->localeNegotiator->applyLocale($request);                       // sets the request locale; call after LocaleListener (priority 16)
$this->localeNegotiator->addLanguageHeaders($request, $response);     // Content-Language + Vary: Accept-Language
```

`ApiDocListener` uses it; the documentation output did not change.

## 10.4.1

- **An adapter's routes can be imported once per API group.** Importing the same adapter `router.yaml` twice (e.g. `/mcp` and `/mcp/internal`) produced identical route names, and the second import replaced the first. An adapter now registers its `router.yaml` with the RPC YAML loader instead of extending the loader:

  ```php
  use Ufo\JsonRpcBundle\Routing\IApiRouteImport;

  final class McpRouteImport implements IApiRouteImport
  {
      public function getResource(): string { return __DIR__.'/../../config/router.yaml'; }
      public function getRouteNamePrefixAttribute(): string { return '_mcp_name_prefix'; }
  }
  ```

  Autoconfigured implementations are tagged `ufo.rpc.route_import`. Each import then gets route names `<group>_<name>` and the `<group>_<name_prefix>` value in the route default `getRouteNamePrefixAttribute()` (aliases without the group are added for `general` only). Registrations apply whichever loader wins the `routing.loader` priority, so several adapters work side by side; extending `RpcYamlFileLoader` (as rest-bundle does) keeps working as before.

## 10.4.2

- **One entry point for adapters: `RpcExecutor`.** rest-bundle and mcp-bundle execute RPC methods outside the core route and each repeated the request preparation and the error handling its own way. `Ufo\JsonRpcBundle\Server\RpcExecutor::execute(IRpcRequestCarrier $carrier): RpcResponse` sets the carrier, executes the request with its own event pool and always returns a response: an exception that escapes the handler goes through `rpc.error` (error response and logging as in the core). A non-RPC exception is wrapped the way `RpcServerListener` does it, so the error `data` is filtered by environment on every path. The token is not checked: every adapter checks it its own way. `InlineBatchStrategy` uses the same code.

  ```php
  $response = $this->rpcExecutor->execute(new RpcFromMcp($rpcRequest));
  ```

- **`rpc.post_response` for any carrier.** It used to fire only for `RpcFromHttp`, so for REST calls cache relations and async/notification listeners did not run. Requests are now collected in `PostResponseQueue` and fired on `kernel.terminate` / `console.terminate`, once per executed request, also when one HTTP request executes several (an MCP batch; unprocessed items of a process-mode batch, of which only the first one used to get it). A carrier is tied to its HTTP request by the new `IRpcHttpCarrier` interface (`getHttpRequest()`), implemented by `RpcFromHttp`; `notification.process` detaches a single request of any `IRpcHttpCarrier`.
- **No `rpc.post_response` for an error response, now explicitly.** It never fired (a `TypeError` on the `null` service was swallowed); the queue now skips such requests. A failing `rpc.post_response` listener is logged instead of being swallowed silently.

## 10.4.3

- **Parameters are converted again without `#[RPC\Param]`.** Since 10.0.6 the service map built by the compiler pass got a `ChainParamConvertor` without convertors (`#[AutowireIterator]` is not resolved at that compilation stage), so a parameter typed as an enum, `DateTimeInterface`, `Uuid` or a Doctrine entity was passed to the method as it came in the request: `-32602 Parameter "color" must be of type Color, string given`, also when it was not passed and its default applied. The pass now builds one `ChainParamConvertor` with the tagged convertors.
- **Positional params are converted too**, matched to the parameters by position; a parameter that is not passed keeps the method's default.
- **The default of a converted parameter is kept** in the service map and the documentation (it was replaced by `null`).
- **Responses in one process no longer share serialization groups.** `RpcResponseContextBuilder` kept the groups of previous responses, so in a long-running worker or an MCP batch an error response after a success got `"result": null`, a success after an error got `"error": null`, and an object result after an error was normalized as `[]`. Fixed in `ufo-tech/rpc-objects` 3.9.2, now required.

## 10.4.4

- **An unknown enum value is a parameter error, not a server error.** `BackedEnumConvertor` and `EnumConvertor` threw `RuntimeException("Invalid value 'xml' for enum <FQCN>")`, so a client error looked like an internal server error and REST answered `500`. It is now `RpcBadParamException('Invalid value "xml", expected one of: json, csv')`, without the class name in the message.
- **An invalid UUID too.** `UuidConverter` let the library exception (`symfony/uid` or `ramsey/uuid`) through as is. It is now `RpcBadParamException('Invalid value "…", expected a UUID')`.
- **All conversion errors in one response.** `ParamsConverterEventListener` collects the convertor errors of every parameter (per index for a collection) and returns them as validation errors: `-32602`, `message: "Invalid Data for call method: <method>"`, `data: {"format": ["Invalid value \"xml\", expected one of: json, csv"]}` or `{"ids": {"2": [...]}}`. The request used to stop at the first error. REST (`ufo-tech/rest-bundle`) answers it with `400` and `detail-data`.
- **A backed enum accepts only values of its type.** An int enum takes an integer or an integer string (`"0"`, `"-3"`); a string enum takes a string or an integer. `tryFrom` used to get the value as is, and PHP's weak typing silently turned `false`, `0.0`, `"0.0"` and `null` into the case with value `0`, and `1.0` into case `1`. These are now `-32602`. A `bool` passed to an enum parameter is no longer cast to a number and gives a `-32602` about the parameter type. `null` for an optional parameter with `= null` still becomes `null`, now without a deprecation.
- **Nullable parameters are converted again without `#[RPC\Param]`.** In 10.4.3 a convertor was attached automatically only to a parameter with a single type, so `?SortDirection $dir = null` or `?DateTimeImmutable $date = null` got the request string as is (`-32602 Parameter "dir" must be of type ?SortDirection, string given`). `ParamFiller` now takes the single class of a nullable type (`?T`, `T|null`) and adds `null` to the type of the `Param` attribute.
- **`null` only for a parameter that allows it.** The convertors (enum, date, UUID) return `null` only for a `null` value and only if the parameter is nullable or defaults to `null` (`ParamDefinition::acceptsNull()`). An invalid value of such a parameter is now `-32602`; an enum used to become `null` silently, so e.g. a profile with an invalid sort direction lost the stored one. `null` for `?DateTimeImmutable` is `null`, not the current time; `null` for `?Uuid` is `null`, not an error.
- Tests: `EnumConvertor` (had none), an enum with value `0` for int and string enums, an invalid enum and UUID value through the endpoint.

## Upgrade notes

- **Partial base translation**: if `rpc.<locale>.yaml` has only `title` for a method, `description` now comes from the base translation in `kernel.default_locale` (step 4) instead of the attribute / PHPDoc.
- **Locales** offered by the documentation now include the locales of group files and extension directories.
- **Constructors** (only if you create them manually): `ApiDocListener` has a new optional trailing `?RpcLocaleNegotiator`.
- New config node `ufo_json_rpc.translations.paths` (optional, default `[]`).
- **10.4.1**, constructors: `RpcYamlFileLoader` has a new optional trailing `iterable $routeImports`.
- **10.4.2**, behaviour: REST calls (and any carrier other than `RpcFromHttp` that implements `IRpcHttpCarrier` or goes through `RpcExecutor`) now get `rpc.post_response`: cache relations and async/notification listeners start working for them. Every executed request of a process-mode batch gets it, not only the first one.
- **10.4.2**, constructors (only if you create them manually): `InlineBatchStrategy` has new optional trailing `?RpcExecutor` and `?PostResponseQueue`; `SymfonyFlowListener` has a new optional trailing `?PostResponseQueue`.
- **10.4.3**, documentation: a parameter converted by a convertor (enum, `DateTimeInterface`, `Uuid`, Doctrine entity) is described by its request type (e.g. `string|null` for a date instead of `object`). Methods that used to fail with such a parameter now execute.
- **10.4.4**, error message: for an invalid parameter converted by a convertor, `error.message` is now `Invalid Data for call method: <method>` and the convertor's message is in `error.data`. This also applies to dates (the `message` used to be `Value "…" is not a valid date…`) and to your own convertors that throw `RpcBadParamException`. A client that shows or parses `error.message` (including the PHP SDK exception) gets a different text; the `-32602` code is unchanged.
- **10.4.4**, enums: an int enum no longer accepts floats (`1.0`), strings with a fraction or spaces (`"1.0"`, `" 1"`) or `bool`; a required enum parameter does not accept `null`.
- **10.4.4**, REST: an unknown enum value or an invalid UUID is `400` instead of `500`.
- **10.4.4**, nullable parameters: an invalid value of `?Enum $x = null` is `-32602` instead of `null`; an explicit `null` for `?Enum $x` without a default is `null` instead of an error; `null` for `?DateTimeImmutable` is `null` instead of the current time.
- **10.4.4**, your code: `RpcBadParamException` does not extend `RuntimeException`. If you call `BackedEnumConvertor`, `EnumConvertor` or `UuidConverter` directly and catch `RuntimeException`, catch `RpcBadParamException`.

## Known issues

- A parameter typed `DateTimeInterface` rejects any date (`DateTimeConverter` tries to instantiate the interface); use `DateTimeImmutable` or `DateTime`. An explicit `null` for a non-nullable date parameter becomes the current time.
- `UuidConverter`: a parameter typed `Symfony\Component\Uid\AbstractUid` is not converted; a parameter typed `Ramsey\Uuid\Uuid` or `Ramsey\Uuid\Rfc4122\UuidV*` fails with a type error because `Uuid::fromString()` returns `LazyUuidFromString` — use `Ramsey\Uuid\UuidInterface`.
- The list of locales is shared by all API groups. If `pl` exists only in `rpc/bfa.pl.yaml`, the documentation of another group requested with `Accept-Language: pl` gets `Content-Language: pl` while its texts are in the default locale.
