[English](README.md)

# 6.4 — Symfony 7, документація OpenRPC, кеш відповідей і транспорт Messenger

Теги: 6.4.0. Перший реліз: 2024-07-07.

## Головне

- Базові вимоги: Symfony 7 / PHP 8.3.
- Специфікація API у форматі OpenRPC на `GET <prefix>/openrpc.json`; старий формат документації `GET` позначено як застарілий.
- Кешування відповідей методу через `#[RPC\Cache]`; карта сервісів кешується в `prod`.
- Асинхронні запити через Symfony Messenger: DSN `async.rpc_async` створює транспорт і обробник для `RpcAsyncRequest`.
- Нова конфігурація: метадані проєкту (`project_name`, `project_description`, `project_version`) і опції `docs`; типізовані об'єкти конфігурації в `ConfigService\*`.
- Валідацію перенесено в `ufo-tech/rpc-objects`; метод більше не виконується, якщо параметри не пройшли валідацію.

## Що нового

### Документація OpenRPC

`GET <prefix>/openrpc.json` (`OpenRpcAdapter`, на базі `psx/openrpc`) повертає документ OpenRPC: `info` з метаданих проєкту, сервер з URL API, а для кожного методу — параметри (з JSON Schema з обмежень), тип результату й опис з `@return`, а також тег з класом процедури. Старий документ `GET <prefix>` тепер містить ключі `WARNING` і `NEW_FORMAT`, що посилаються на новий формат.

**Як використати:**

```yaml
# config/packages/ufo_json_rpc.yaml
ufo_json_rpc:
    project_name: 'Billing API'
    project_description: 'Invoices and payments'
    project_version: '1.2.0'
```

```console
$ curl https://example.com/api/openrpc.json
```

### Кеш відповідей (`#[RPC\Cache]`)

Результат методу з атрибутом `Ufo\RpcObject\RPC\Cache` зберігається в кеші застосунку Symfony (`CacheInterface`) за ключем з імені методу та SHA-256 хешу параметрів. Аргументи: `lifetimeSecond` (за замовчуванням 60) і `environments` (за замовчуванням `['prod']`); в інших середовищах кеш не використовується.

**Як використати:**

```php
use Ufo\RpcObject\RPC;

class RatesProcedure implements IRpcService
{
    #[RPC\Cache(lifetimeSecond: 600, environments: ['prod'])]
    public function getRates(string $currency): array { /* ... */ }
}
```

У `prod` також кешується побудована карта сервісів (ключ `rps.service_locator`), тож рефлексія методів не повторюється на кожен запит. Очищайте кеш застосунку після деплою змінених процедур.

### Асинхронний транспорт через Symfony Messenger

Якщо задано `async.rpc_async`, пакет додає (prepend) конфігурацію `framework.messenger`: транспорт `rpc_async` (і `failed`, якщо задано) та маршрутизацію `Ufo\RpcObject\RpcAsyncRequest` у `rpc_async`. `Server\Async\RpcAsyncProcessor` — це `#[AsMessageHandler]`, який виконує запит (з токеном із повідомлення) і виводить запит/відповідь у вивід воркера. Пакет споживає ці повідомлення; їх відправка — справа клієнтської сторони (не входить у цей пакет).

**Як використати** (потрібен `symfony/messenger`, якого немає в `require` пакета):

```yaml
ufo_json_rpc:
    async:
        rpc_async: '%env(resolve:RPC_TRANSPORT_DSN)%'
        # failed: '%env(resolve:RPC_FAILED_DSN)%'
```

```console
$ php bin/console messenger:consume rpc_async
```

### Опції документації (`docs.response`)

- `key_for_methods` — ключ для списку методів у документації `GET` (у шаблоні — `methods`).
- `async_dsn_info` — додати `transport.async` (розібраний з DSN `rpc_async`) у документацію `GET`.
- `validations.symfony_asserts` — додати `symfony_assertions` до кожного методу.
- `validations.json_schema` — додати `json_schema` до кожного методу (за замовчуванням `true`; позначено застарілим, «буде видалено у 7.0»).

**Як використати:**

```yaml
ufo_json_rpc:
    docs:
        response:
            key_for_methods: methods
            async_dsn_info: false
            validations:
                symfony_asserts: true
```

### Типізовані сервіси конфігурації

`ConfigService\RpcMainConfig` (разом з `RpcSecurityConfig`, `RpcDocsConfig`, `RpcAsyncConfig`) — сервіс з нормалізованою конфігурацією, середовищем і URL поточного запиту; його можна інжектити у свій код.

## Зміни та виправлення

- Валідацію виконує `Ufo\RpcObject\Rules\Validator\RpcValidator` (з `ufo-tech/rpc-objects`); виклик методу відбувається лише після успішної валідації.
- Документація `GET`: `transport` тепер об'єкт (`sync` URL/метод, опційно `async`), envelope — `JSON-RPC-2.0/UFO-RPC-<версія пакета>`, додано `name`/`description` проєкту, блок `readDocs` з посиланнями, ключ методу `decription` перейменовано на `description`, опційно `throws`.
- Неперехоплені винятки на API-маршрутах: відповідь використовує `id` запиту (для batch — помилка на кожен елемент) і HTTP 401 для `RpcTokenNotFoundInHeaderException`, 403 для `RpcInvalidTokenException`, 200 в інших випадках.
- Опис з `@return` включено в документацію методів.
- Асинхронні процеси batch правильно знаходять `bin/console` при запуску з CLI; порожній токен не передається в `ufo:rpc:process`.
- Доставка на callback надсилає відповідь, серіалізовану в JSON.
- Контекст серіалізації відповідей уніфіковано в `Serializer\RpcResponseContextBuilder`.
- Відома проблема в 6.4.0: `ApiController::OPEN_RPC_ROUTE` і `COLLECTION_ROUTE` обидва дорівнюють `ufo_rpc_api_collection`; з двох маршрутів з однаковим іменем Symfony залишає останній зареєстрований, тож `GET <prefix>/method/{method}` може бути недоступний. Перевірте `bin/console debug:router` у своєму застосунку.

## Примітки до оновлення

- Вимоги: PHP `>=8.3`, Symfony `^7.0`, `ufo-tech/rpc-objects >=2.3.1`, `ufo-tech/rpc-exceptions >=1.0.3`, новий `psx/openrpc v0.1.1`.
- Видалено: `Interfaces\IRpcValidator` і `Validations\RpcValidator` (використовуйте валідатор з `rpc-objects`).
- `ITokenValidator::isValid()` тепер має тип повернення `true` (у разі невдачі кидайте `RpcInvalidTokenException`); `SimpleTokenValidator`, `TokenRpcSecurity`, `TokenRpcCliSecurity` приймають `RpcMainConfig` замість скалярних аргументів. Оновіть власні реалізації та визначення сервісів.
- Значення за замовчуванням: `RpcMainConfig` доповнює відсутні у вашій конфігурації ключі значеннями з шаблону пакета `install/packages/ufo_json_rpc.yaml`. Згідно з кодом, якщо секцію `security` не задано, застосовуються `protected_methods: ['POST']` і приклади токенів із шаблону. Завжди задавайте `security` явно.
- Клієнти, які розбирають старий документ `GET`, мають адаптуватися до нової структури (див. вище) або перейти на `openrpc.json`.
