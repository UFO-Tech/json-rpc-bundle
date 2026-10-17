[English](README.md)

# 8.0 — Подієва обробка запитів, конвертори параметрів, єдина безпека для HTTP/CLI

Теги: 8.0.0, 8.0.1, 8.0.2, 8.0.3, 8.0.4, 8.0.5. Перший реліз: 2025-06-05.

## Головне

- **Обробка запитів перенесена в події Symfony**: POST на RPC-ендпоінт обробляє listener `kernel.request`; `ApiController` віддає лише документацію.
- **Події життєвого циклу RPC тепер у бандлі** (`Ufo\JsonRpcBundle\EventDrivenModel\Events\*`), зокрема нова `rpc.post_response`, що спрацьовує після відправлення відповіді.
- **Конвертори параметрів**: перетворення скаляр ↔ об'єкт для `BackedEnum`, `DateTime*` та UUID (`symfony/uid`, `ramsey/uuid`), розширюється через `IParamConvertor`.
- **Одна модель безпеки для HTTP, CLI та async** через token holders; `ufo:rpc:process` приймає токен як `--<token_name>` і підтримує batch-запити.
- **Ліниве кешування опису кожного сервісу окремо** (`ServiceHolder`).

## Що нового

### Подієва обробка HTTP та CLI

`SymfonyFlowListener` обробляє RPC-ендпоінт на `kernel.request` (POST → `RpcRequestHandler`), а `ufo:rpc:process` — на `console.command`. Сирий запит розбирає request carrier (`RpcFromHttp`, `RpcFromCli`), який зберігається в `RequestCarrier`; одиночні та batch-запити підтримуються і в HTTP, і в CLI. Запити `OPTIONS` та з `X-Requested-With: XMLHttpRequest` на ендпоінт отримують порожню відповідь `200`. Помилки перетворює на JSON-RPC-відповіді `SymfonyErrorListener` (HTTP і консоль).

**Як використовувати:** налаштовувати нічого не треба. Виклик з CLI:

```bash
php bin/console ufo:rpc:process '{"id":1,"method":"ping","params":{}}' --ufo-rpc-token=<token>
```

Назва опції токена — `token_name` у нижньому регістрі (за замовчуванням `Ufo-RPC-Token` → `--ufo-rpc-token`). Вона обов'язкова лише при `security.protected_api: true`.

### Події життєвого циклу RPC

Події та їхні назви (константи `RpcEvent`):

| Назва | Клас | Дані |
|---|---|---|
| `rpc.request` | `RpcRequestEvent` | `rpcRequest` |
| `rpc.async_request` | `RpcAsyncRequestEvent` | `rpcRequest` |
| `rpc.async_output` | `RpcAsyncOutputEvent` | `rpcRequest`, `batchRequest`, `output` |
| `rpc.pre_execute` | `RpcPreExecuteEvent` | `rpcRequest`, `service`, `params` (можна змінювати) |
| `rpc.post_execute` | `RpcPostExecuteEvent` | `result`, `rpcRequest`, `service`, `params` |
| `rpc.pre_response` | `RpcPreResponseEvent` | `response`, `rpcRequest`, `service` |
| `rpc.post_response` | `RpcPostResponseEvent` | `response`, `rpcRequest`, `service` |
| `rpc.error` | `RpcErrorEvent` | `rpcRequest`, `exception`, `rpcError` (можна замінити) |

`rpc.post_response` викликається на `kernel.terminate` / `console.terminate`, тобто після того, як клієнт отримав відповідь.

**Як використовувати:**

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPostResponseEvent;

#[AsEventListener(RpcEvent::POST_RESPONSE)]
final class AuditListener
{
    public function __invoke(RpcPostResponseEvent $event): void
    {
        // $event->rpcRequest->getMethod(), $event->response ...
    }
}
```

### Конвертори параметрів

`ChainParamConvertor` збирає сервіси з тегом `rpc.param_convertor` (autoconfigure для `IParamConvertor`). Вбудовані: `BackedEnumConvertor`, `DateTimeConverter` (`DateTime`, `DateTimeImmutable`, `DateTimeInterface`; формат виводу `Y-m-d H:i:s`), `UuidConverter` (`Symfony\Component\Uid\*` та `Ramsey\Uuid\*`). Вхідні значення конвертуються для параметрів з атрибутом `#[RPC\Param]` (`ufo-tech/rpc-objects`); у DTO-результаті властивості, чий реальний тип — клас, перед відповіддю перетворюються назад на скаляри. Конструктор `RPC\Param` належить `ufo-tech/rpc-objects` і в цьому бандлі не задокументований.

**Як використовувати** — власний конвертор:

```php
use Ufo\JsonRpcBundle\ParamConvertors\IParamConvertor;

final class MoneyConvertor implements IParamConvertor
{
    public function toScalar(object $object, array $context = [], ?callable $callback = null): string|int|float|null
    {
        return $object->amount;
    }

    public function toObject(int|string|float|null $value, array $context = [], ?callable $callback = null): ?object
    {
        return new Money((int) $value);
    }

    public function supported(string $classFQCN): bool
    {
        return $classFQCN === Money::class;
    }
}
```

З увімкненим autoconfigure клас реєструється автоматично.

### Єдина безпека (token holders)

`IRpcSecurity` тепер працює з `IRpcTokenHolder`: `HttpTokenHolder` (заголовок `token_name`), `CliTokenHolder` (опція `--<token_name>`), `RpcAsyncTokenHolder` (поле `token` async-повідомлення). `TokenRpcSecurity` надає `isValidApiRequest()` та `isValidDocRequest()`; маршрути документації перевіряє `DocSecurityListener`, якщо `protected_doc: true`.

**Як використовувати:** ключі конфігурації не змінилися:

```yaml
ufo_json_rpc:
    security:
        protected_api: true
        protected_doc: false
        token_name: 'Ufo-RPC-Token'
        clients_tokens:
            - '%env(resolve:UFO_API_TOKEN)%'
```

### Кешування опису кожного сервісу окремо

Опис кожної процедури кешується окремо (ключ `service_<method>`) і завантажується на вимогу через `ServiceHolder`; додаткові джерела можна підключити, реалізувавши `IServiceHolder` (тег `rpc.service_holder`). Ключ кешу service map змінився з `rps.service_map` на `rpc.service_map`.

**Як використовувати:** налаштовувати нічого не треба; після оновлення виконайте `php bin/console cache:clear`.

## Зміни та виправлення

- 8.0.0: Async-процеси: таймаут за замовчуванням збільшено з 60 до 3600 с; помилка async-підзапиту стає error-відповіддю для цього запиту, а не ламає весь batch.
- 8.0.0: Усі value-об'єкти конфігурації мають значення за замовчуванням (`RpcSecurityConfig`, `RpcDocsConfig`, `RpcAsyncConfig`), тож відсутні ключі більше не спричиняють notice.
- 8.0.0: `ConstraintsImposedException` перетворюється на `RpcError` зі списком порушених обмежень (`RpcErrorListener` на `rpc.error`).
- 8.0.1: Рефлексію DTO обмежено 3 рівнями вкладеності (`DtoReflector::DEPTH_SIZE`), щоб уникнути нескінченної рекурсії.
- 8.0.2: Перед `rpc.post_response` викликається `fastcgi_finish_request()`, тож post-response listeners не затримують клієнта (PHP-FPM).
- 8.0.3: OpenRPC: параметри з типом DTO-класу отримують повну схему об'єкта; listeners валідації параметрів тепер виконуються до конвертації; ненульовий параметр більше не позначається необов'язковим через `null` за замовчуванням.
- 8.0.4: OpenRPC: параметри та властивості `BackedEnum` отримують `enum` + `type` і UFO-метадані enum (`UfoEnumsHelper`); обмеження `Choice` з enum-callback теж їх додають; ключі `x-ufo*` беруться з `XUfoValuesEnum`.
- 8.0.5: `RequestCarrier::getSpecialParams()` повертає спеціальні параметри запиту (`getSpecialParams()`), а не всі параметри.

## Що врахувати при оновленні

З 7.6:

- Класи подій перенесено з `Ufo\RpcObject\Events\*` до `Ufo\JsonRpcBundle\EventDrivenModel\Events\*`. Оновіть `use` у своїх listeners.
- `ApiController::serverAction()` обробляє лише `GET` (OpenRPC). POST обробляє `SymfonyFlowListener`; код, що викликав `RpcRequestHandler::handle(Request $request)`, має використовувати `handle()` (запит береться з `RequestCarrier`). Константи `ApiController::COLLECTION_ROUTE` та `OPEN_RPC_ROUTE` видалено.
- Змінено API безпеки: методи `IRpcSecurity` тепер `setTokenHolder()`, `getTokenHolder()`, `isValidApiRequest()`, `isValidDocRequest()` (старі `getTokenHeaderKey()`, `getToken()`, `isValidToken()`, `isValidRequest()` видалено). Видалено `TokenRpcCliSecurity`, `Security\Helper`, `SecurityListener`. Власні реалізації `IRpcSecurity` треба переписати.
- `RpcSecurityConfig`: властивість `tokenKeyInHeader` → `tokenName`, константа `TOKEN_KEY` → `TOKEN_NAME`; `protectedMethods` видалено. YAML-ключ `token_name` не змінився.
- `ufo:rpc:process`: опцію `-t/--token` замінено на `--<token_name у нижньому регістрі>`; аргумент `json` став необов'язковим.
- Видалено: `CurrentRpcRequestHolder`, `RpcRequestHelper` (використовуйте `RequestCarrier`), `CliRpcListener`, `DTOToParamsEventListener` (замінено на `ParamsConverterEventListener`), `ServiceMapFactory::getServiceLocator()`. `Exceptions\StopHandlerException` перейменовано на `Exceptions\StopHandler`. `RpcServer` залежить від `IServiceHolder` замість `ServiceMap`.
- Помилка відсутнього токена — `RpcTokenNotSentException` (HTTP 401) з `ufo-tech/rpc-exceptions` ^1.1.
- Нові залежності: `symfony/yaml`, `symfony/uid`, `ramsey/uuid` ^4, `ufo-tech/rpc-exceptions` ^1.1.
- Очистіть кеш: ключ кешу service map змінився.
