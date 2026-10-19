[English](README.md)

# 10.4 — Переклади для груп API, переклади з розширень, вибір мови

Теги: 10.4.0, 10.4.1, 10.4.2, 10.4.3. Перший реліз: 2026-10-18.

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

## 10.4.1

- **Маршрути адаптера можна підключити окремо для кожної групи API.** Два імпорти того самого `router.yaml` адаптера (наприклад, `/mcp` і `/mcp/internal`) давали однакові імена маршрутів, і другий імпорт затирав перший. Тепер адаптер реєструє свій `router.yaml` у YAML-loader-і RPC замість наслідування loader-а:

  ```php
  use Ufo\JsonRpcBundle\Routing\IApiRouteImport;

  final class McpRouteImport implements IApiRouteImport
  {
      public function getResource(): string { return __DIR__.'/../../config/router.yaml'; }
      public function getRouteNamePrefixAttribute(): string { return '_mcp_name_prefix'; }
  }
  ```

  Автоконфігуровані реалізації отримують тег `ufo.rpc.route_import`. Кожен імпорт тоді отримує імена маршрутів `<group>_<name>` і значення `<group>_<name_prefix>` в атрибуті маршруту з `getRouteNamePrefixAttribute()` (аліаси без групи — лише для `general`). Реєстрації працюють незалежно від того, який loader переміг за пріоритетом `routing.loader`, тож кілька адаптерів працюють поруч; наслідування `RpcYamlFileLoader` (як у rest-bundle) працює як і раніше.

## 10.4.2

- **Одна точка входу для адаптерів: `RpcExecutor`.** rest-bundle і mcp-bundle виконують RPC-методи поза маршрутом core, і кожен по-своєму повторював підготовку запиту й обробку помилок. `Ufo\JsonRpcBundle\Server\RpcExecutor::execute(IRpcRequestCarrier $carrier): RpcResponse` ставить carrier, виконує запит з окремим пулом подій і завжди повертає відповідь: виняток, що вилетів з обробника, проходить через `rpc.error` (відповідь з помилкою й логування як у core). Не-RPC виняток загортається так само, як у `RpcServerListener`, тож `data` помилки фільтрується за оточенням на кожному шляху. Токен не перевіряється: кожен адаптер перевіряє його по-своєму. `InlineBatchStrategy` використовує той самий код.

  ```php
  $response = $this->rpcExecutor->execute(new RpcFromMcp($rpcRequest));
  ```

- **`rpc.post_response` для будь-якого carrier-а.** Раніше подія запускалася лише для `RpcFromHttp`, тож для REST-викликів не працювали зв'язки кешу й async/notification-слухачі. Тепер запити збираються в `PostResponseQueue` і запускаються на `kernel.terminate` / `console.terminate` один раз на кожен виконаний запит, також коли один HTTP-запит виконує кілька (batch у MCP; невиконані елементи batch у process-режимі, з яких раніше подію отримував лише перший). Carrier прив'язується до свого HTTP-запиту новим інтерфейсом `IRpcHttpCarrier` (`getHttpRequest()`), його реалізує `RpcFromHttp`; `notification.process` відокремлює одиночний запит будь-якого `IRpcHttpCarrier`.
- **Для відповіді з помилкою `rpc.post_response` не запускається, тепер явно.** Вона й раніше не запускалася (`TypeError` на `null` service ковтався); тепер черга пропускає такі запити. Помилка слухача `rpc.post_response` логується, а не ковтається мовчки.

## 10.4.3

- **Параметри знову перетворюються без `#[RPC\Param]`.** З 10.0.6 мапа сервісів, яку будує compiler pass, отримувала `ChainParamConvertor` без конверторів (`#[AutowireIterator]` на цьому етапі компіляції ще не розгорнуто), тож параметр з типом enum, `DateTimeInterface`, `Uuid` чи Doctrine-сутність потрапляв у метод таким, як прийшов у запиті: `-32602 Parameter "color" must be of type Color, string given`, також коли його не передали і спрацьовувало значення за замовчуванням. Тепер pass будує один `ChainParamConvertor` з тегованими конверторами.
- **Позиційні params теж перетворюються**, за позицією параметра; параметр, якого не передали, отримує значення за замовчуванням з методу.
- **Значення за замовчуванням перетвореного параметра зберігається** в мапі сервісів і документації (раніше його заміняв `null`).
- **Відповіді в одному процесі більше не ділять групи серіалізації.** `RpcResponseContextBuilder` зберігав групи попередніх відповідей, тож у long-running воркері чи MCP batch відповідь з помилкою після успішної отримувала `"result": null`, успішна після помилки — `"error": null`, а результат-об'єкт після помилки нормалізувався як `[]`. Виправлено в `ufo-tech/rpc-objects` 3.9.2, тепер це мінімальна версія.

## Що врахувати при оновленні

- **Частковий базовий переклад**: якщо в `rpc.<locale>.yaml` для методу є лише `title`, `description` тепер береться з базового перекладу мовою `kernel.default_locale` (крок 4), а не з атрибута / PHPDoc.
- **Мови**, які пропонує документація, тепер включають мови групових файлів і тек розширень.
- **Конструктори** (лише якщо створюєте вручну): `ApiDocListener` має новий необов'язковий останній аргумент `?RpcLocaleNegotiator`.
- Новий вузол конфігу `ufo_json_rpc.translations.paths` (необов'язковий, за замовчуванням `[]`).
- **10.4.1**, конструктори: `RpcYamlFileLoader` має новий необов'язковий останній аргумент `iterable $routeImports`.
- **10.4.2**, поведінка: REST-виклики (і будь-який carrier, крім `RpcFromHttp`, що реалізує `IRpcHttpCarrier` або йде через `RpcExecutor`) тепер отримують `rpc.post_response`: для них запрацюють зв'язки кешу й async/notification-слухачі. У batch у process-режимі подію отримує кожен виконаний запит, а не лише перший.
- **10.4.2**, конструктори (лише якщо створюєте вручну): `InlineBatchStrategy` має нові необов'язкові останні аргументи `?RpcExecutor` і `?PostResponseQueue`; `SymfonyFlowListener` має новий необов'язковий останній `?PostResponseQueue`.
- **10.4.3**, документація: параметр, який перетворює конвертор (enum, `DateTimeInterface`, `Uuid`, Doctrine-сутність), описується типом у запиті (наприклад, `string|null` для дати замість `object`). Методи, що падали з таким параметром, тепер виконуються.

## Відомі проблеми

- Список мов спільний для всіх груп API. Якщо `pl` є лише в `rpc/bfa.pl.yaml`, документація іншої групи з `Accept-Language: pl` отримає `Content-Language: pl`, хоча тексти в ній мовою за замовчуванням.
