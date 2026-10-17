[English](README.md)

# 10.0 — версії API, нормалізована документація, карта сервісів під час компіляції

Теги: 10.0.0, 10.0.1, 10.0.2, 10.0.3, 10.0.5, 10.0.6. Перший реліз: 2026-02-26.

## Головне

- **Версії API**: кілька версій одного API одночасно (`/rpc`, `/rpc/v2`, ...); нова версія успадковує попередню й може прибирати методи.
- **Нормалізовані OpenRPC/Postman**: методи згруповані за процедурою, помилки з `@throws`, enum і DTO — спільні посилання `#/components/schemas`, `info.x-versions`.
- **Карта сервісів будується під час компіляції контейнера** (`RpcServiceMapPass`), а не під час запиту.
- **Автоматична конвертація параметрів**: параметр із класом, який підтримує конвертор (UUID, DateTime, enum, Doctrine-сутність), конвертується без `#[RPC\Param]`.
- **Конвертор Doctrine-сутностей**: передаєте id — отримуєте сутність.

## Що нового

### Версії API

Клас процедури задає версію в `#[RPC\Info]` (`version` — ціле число, за замовчуванням `1`, публікується як `v1`). Версія N містить усі методи нижчих версій; однойменні методи замінюються новішим класом, методи з `removedMethods` прибираються.

**Як використовувати**

```php
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

#[RPC\Info('user')]                       // v1
class UserApi implements IRpcService
{
    public function get(int $id): array { /* ... */ }
    public function legacySearch(string $q): array { /* ... */ }
}

#[RPC\Info('user', version: 2, removedMethods: ['legacySearch'])]
class UserApiV2 implements IRpcService
{
    public function get(string $uuid): array { /* ... */ }   // замінює user.get у v2
}
```

| Точка входу | Версія |
| --- | --- |
| `POST /rpc`, `GET /rpc`, `GET /rpc/postman` | `v1` (за замовчуванням) |
| `POST /rpc/v2`, `GET /rpc/v2`, `GET /rpc/v2/postman` | `v2` |
| `bin/console ufo:rpc:process '<json>' --ver=v2` | `v2` |

`removedMethods` приймає назви методів без аліасу. Назви маршрутів: `ufo_rpc_api_server` / `ufo_rpc_api_server_ver`, `ufo_rpc_api_postman` / `ufo_rpc_api_postman_ver`. Документ OpenRPC перелічує всі зареєстровані версії в `info.x-versions`.

### Нормалізована документація

- Кожен метод має тег з аліасом процедури (`user`) замість FQCN класу; клас іде в опис тегу.
- Помилки методу генеруються з `@throws` у docblock (код і повідомлення кожного винятку).
- Enum виносяться в `#/components/schemas/<Enum>` і підключаються через `$ref`, тож кожен enum описаний один раз.
- Postman: назва колекції `RPC: <project_name> <version> (...)`, `info.version`, змінна `base_url`; заголовок/змінну `AccessToken` прибрано, лишився тільки заголовок токена з `token_name`.

### Карта сервісів під час компіляції

`RpcServiceMapPass` збирає всі класи `IRpcService` під час компіляції контейнера й зберігає карту в параметрі контейнера; `ServiceMap` лише створює об'єкти `Service` на вимогу. `ServiceMapFactory` і `ServiceLocator` видалено; RPC-класи доступні через service locator `rpc.service_locator`.

### Автоматична конвертація параметрів

Якщо параметр не має `#[RPC\Param]`, а його тип підтримує зареєстрований конвертор, автоматично застосовується `Param` цього конвертора. Вбудовані конвертори: `UuidConverter`, `DateTimeConverter`, `BackedEnumConvertor`, `EnumConvertor` (лише pure enum), `DoctrineEntityConverter`.

**Як використовувати**

```php
#[RPC\Info('order')]
class OrderApi implements IRpcService
{
    // запит: {"method": "order.status", "params": {"order": 42}}
    public function status(Order $order): string { return $order->getStatus(); }   // Order — Doctrine-сутність
}
```

У відповідях властивості поверненого об'єкта, для типів яких є конвертор (сутність → ідентифікатор, enum → значення, UUID/DateTime → рядок), перетворюються на скаляри, навіть якщо метод не має `#[RPC\ResultAsDTO]`.

Власні конвертори реалізують `Ufo\JsonRpcBundle\ParamConvertors\IParamConvertor` (тег `rpc.param_convertor`) і тепер мають реалізувати `getParamAttr(string $classFQCN): Param`.

### Конвертор Doctrine-сутностей

`DoctrineEntityConverter` завантажує сутність за id через `ManagerRegistry`, а у відповідях повертає її ідентифікатор (або `__toString()` для `Stringable`). З 10.0.1 він реєструється лише тоді, коли ввімкнено DoctrineBundle (`#[RequiresExtension(RequiresExtension::DOCTRINE)]`).

### Інʼєкція сервісів в аргументи RPC-методів

10.0.0 додав підтримку `#[Autowire]` для аргументів RPC-методів (значення беруться з локатора методу `rpc.service_argument_locator`; такі аргументи не потрапляють у документацію).

**Відома проблема:** починаючи з 10.0.1 (і досі в 10.2.3) `RpcServiceMapPass::processParams()` пропускає кожен типізований параметр (`if ($typeName || ...) continue;`), тому `#[Autowire]` в аргументах методу не реєструється. Використовуйте інʼєкцію через конструктор.

## Зміни та виправлення

- DTO-параметри: значення, з якого не вдається створити DTO, тепер повертає помилку (`ConstraintsImposedException`, "Invalid Data for call method: ...") замість передачі сирого масиву в метод.
- Необов'язковий enum-параметр зі значенням за замовчуванням `null` отримує `null` для невідомого значення замість помилки.
- `Package` тепер успадковує `Ufo\Packages\UfoPackage` (нова залежність `ufo-tech/packages`).
- 10.0.1: необов'язкові сервіси вимагають відповідного розширення Symfony (`RequiresExtension`).
- 10.0.2: `ufo-tech/dto-transformer` ^3.0, `ufo-tech/rpc-objects` ^3.6.
- 10.0.3: `protected_doc` перевіряє токен лише для `GET`-запитів документації й читає його з HTTP-запиту.
- 10.0.5–10.0.6: виправлення схем enum, конверторів параметрів і карти сервісів.

## Примітки до оновлення

- Вимоги: PHP >= 8.3, Symfony ^7.2.
- Оновлюйте `ufo-tech/rpc-objects` і `ufo-tech/dto-transformer` разом із bundle (`RPC\Info` має містити `version` / `removedMethods`).
- У шаблоні встановлення префікс маршруту змінено з `/api` на `/rpc` (`install/routes/ufo_json_rpc.yaml`); наявні файли маршрутів застосунку не змінюються.
- Власний `IParamConvertor`: додайте `getParamAttr()`.
- Власний `IServiceHolder`: `getService(string $serviceName, string $version = 'v1')`, а також нові `getServices(string $version)` і `getVersions()`.
- Видалено: `ServiceMapFactory`, `ServiceLocator`, `ServiceMap::addService()`, `getTarget()`, `getDescription()`. Перенесено: `RpcFromCli` / `RpcFromHttp` до `Server\RequestPrepare\Holders`.
- `Service`: конструктор вимагає `Info $apiClassInfo`; `name`, `procedureFQCN`, `methodName`, `procedure` — public readonly; `getResponseInfo()` повертає `RPC\DTO` замість `ResultAsDTO`. `getName()` працює; `#[Deprecated]` він позначений лише в 10.3 (використовуйте властивість `name`).
- Дії `ApiController` перейменовано: `serverAction()` → `openrpc()`, `postmanAction()` → `postman()`.
- `RPC\Info(concat: ...)` з 10.0 не застосовується до назв методів (завжди `.`). Схоже на ненавмисну зміну; перевірте, якщо використовуєте власний `concat`.
