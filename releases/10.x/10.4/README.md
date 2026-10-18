[Українська](README-UA.md)

# 10.4 — Translations per API group, translations from extensions, locale negotiator

Tags: 10.4.0. First released: 2026-10-18.

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

## Upgrade notes

- **Partial base translation**: if `rpc.<locale>.yaml` has only `title` for a method, `description` now comes from the base translation in `kernel.default_locale` (step 4) instead of the attribute / PHPDoc.
- **Locales** offered by the documentation now include the locales of group files and extension directories.
- **Constructors** (only if you create them manually): `ApiDocListener` has a new optional trailing `?RpcLocaleNegotiator`.
- New config node `ufo_json_rpc.translations.paths` (optional, default `[]`).

## Known issues

- The list of locales is shared by all API groups. If `pl` exists only in `rpc/bfa.pl.yaml`, the documentation of another group requested with `Accept-Language: pl` gets `Content-Language: pl` while its texts are in the default locale.
