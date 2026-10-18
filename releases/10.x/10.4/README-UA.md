[English](README.md)

# 10.4 — Переклади для груп API, переклади з розширень, вибір мови

Теги: 10.4.0. Перший реліз: 2026-10-18.

## Головне

- **Назви й описи методів для окремої групи API**: той самий метод можна описати по-різному в `/rpc` і `/rpc/bfa`.
- **Переклади з розширень**: бандл, що додає RPC-методи, може постачати їхні переклади; застосунок може їх перевизначити.
- **`RpcLocaleNegotiator`**: логіка вибору мови з `Accept-Language`, яку використовує документація, як публічний сервіс для розширень.

## Що нового

### Переклади для груп API

Базовий переклад лишається там, де був (`translations/rpc.<locale>.yaml`). Перевизначення для однієї групи API кладуться в підтеку `rpc/` з іменем `<group>.<locale>.yaml` (або `.yml`):

```
translations/rpc.uk.yaml        # база, для всіх груп
translations/rpc/bfa.uk.yaml    # лише для групи bfa
translations/rpc/bfa.en.yaml
```

```yaml
# translations/rpc/bfa.uk.yaml
catalog.list:
    title: Каталог для партнерів    # description береться з базового перекладу
```

Перевизначення може містити лише `title` або лише `description`; інше поле шукається далі. Кожне поле шукається в такому порядку (група важливіша за мову):

| Крок | Джерело |
| --- | --- |
| 1 | група, мова клієнта |
| 2 | група, `kernel.default_locale` |
| 3 | база, мова клієнта |
| 4 | база, `kernel.default_locale` |
| 5 | `#[RPC\Info]` / PHPDoc |

Мова — останній сегмент імені файлу, група — усе перед ним, тож `rpc/partner.v2.en.yaml` належить групі `partner.v2`. Усе зводиться під час компіляції контейнера; у debug контейнер перебудовується при змінах у `translations/` і `translations/rpc/`.

> **Не називайте групу як домен Symfony Translator** (`messages`, `validators`, `security` або власний). Якщо Translator увімкнено, він сканує `translations/` рекурсивно і вважатиме `rpc/messages.uk.yaml` частиною домену `messages`.

### Переклади з розширень

```yaml
ufo_json_rpc:
  translations:
    paths:
      - '%kernel.project_dir%/vendor/acme/rpc-extension/translations'
```

Кожна тека має ту саму структуру: `rpc.<locale>.yaml` і `rpc/<group>.<locale>.yaml`. Бандл додає свою теку сам у `prepend()`:

```php
public function prepend(ContainerBuilder $container): void
{
    $container->prependExtensionConfig('ufo_json_rpc', [
        'translations' => ['paths' => [__DIR__.'/../../translations']],
    ]);
}
```

Теки зводяться поле за полем; пізніша перекриває попередню, а `translations/` застосунку завжди остання.

### `RpcLocaleNegotiator`

`Ufo\JsonRpcBundle\Server\RpcLocaleNegotiator` вибирає мову з `Accept-Language` так само, як документація (`uk-UA` дає `uk`; без збігу чи без заголовка — перша мова зі списку, тобто мова за замовчуванням, якщо для неї є переклади). За замовчуванням вибирає серед мов RPC-перекладів; розширення з власними перекладами передає свої мови:

```php
public function __construct(private RpcLocaleNegotiator $localeNegotiator) {}

$locale = $this->localeNegotiator->negotiate($request, ['en', 'uk']); // null, якщо список порожній
$this->localeNegotiator->applyLocale($request);                       // ставить мову запиту; викликати після LocaleListener (пріоритет 16)
$this->localeNegotiator->addLanguageHeaders($request, $response);     // Content-Language + Vary: Accept-Language
```

`ApiDocListener` використовує цей сервіс; вивід документації не змінився.

## Що врахувати при оновленні

- **Частковий базовий переклад**: якщо в `rpc.<locale>.yaml` для методу є лише `title`, `description` тепер береться з базового перекладу мовою `kernel.default_locale` (крок 4), а не з атрибута / PHPDoc.
- **Мови**, які пропонує документація, тепер включають мови групових файлів і тек розширень.
- **Конструктори** (лише якщо створюєте вручну): `ApiDocListener` має новий необов'язковий останній аргумент `?RpcLocaleNegotiator`.
- Новий вузол конфігу `ufo_json_rpc.translations.paths` (необов'язковий, за замовчуванням `[]`).

## Відомі проблеми

- Список мов спільний для всіх груп API. Якщо `pl` є лише в `rpc/bfa.pl.yaml`, документація іншої групи з `Accept-Language: pl` отримає `Content-Language: pl`, хоча тексти в ній мовою за замовчуванням.
