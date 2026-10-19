[English](README.md)

# ufo-tech/json-rpc-bundle 10.x

Мажорна версія 10 перетворює bundle на сервер кількох API: версії API (10.0), JSON-RPC нотифікації та Symfony 8 (10.1), кілька груп API / точок входу з власними токенами (10.2), реліз про продуктивність і batch (10.3), а також переклади для груп API і з розширень (10.4). Карта сервісів будується під час компіляції, документація (OpenRPC, Postman) нормалізована й публікується для кожної версії та групи.

10.3 — мінорний реліз, але містить кілька зворотно несумісних змін для коду, що створює або розширює сервіси bundle, і зміни поведінки batch: див. [примітки до оновлення](10.3/README-UA.md#що-врахувати-при-оновленні).

## Вимоги

| | 10.0.0 | 10.2.3 |
| --- | --- | --- |
| PHP | >= 8.3 | >= 8.4 |
| Symfony | ^7.2 | ^7.2 \| ^8.0 |
| ufo-tech/rpc-objects | ^3.4 (^3.6 з 10.0.2) | ^3.9 |
| ufo-tech/dto-transformer | ^2.1 (^3.0 з 10.0.2) | ^3.1 |
| ufo-tech/rpc-exceptions | ^1.3 | ^2 |
| Інше | нова залежність `ufo-tech/packages` ^1 | `symfony/messenger` тепер обов'язковий |

## Версії

| Версія | Головне |
| --- | --- |
| [10.0](10.0/README-UA.md) | Версії API (`/rpc/v2`, `RPC\Info(version:, removedMethods:)`); нормалізовані OpenRPC/Postman (теги процедур, помилки з `@throws`, спільні схеми enum, `x-versions`); карта сервісів під час компіляції; автоматична конвертація параметрів; конвертор Doctrine-сутностей |
| [10.1](10.1/README-UA.md) | Symfony 8; JSON-RPC нотифікації (`204`, виконання після відповіді); сувора перевірка `jsonrpc: "2.0"` і порожнього batch; єдиний формат помилок параметрів |
| [10.2](10.2/README-UA.md) | Групи API: кілька точок входу RPC (public/internal/BFF) через імпорти маршрутів і `#[RPC\APIGroups]`; провайдери токенів для груп (`security.providers`); завантажувачі маршрутів для адаптерів; `--api-group` для CLI і `apiGroup` для Messenger |
| [10.3](10.3/README-UA.md) | Налаштування batch з inline-режимом; правила JSON-RPC нотифікацій у batch; кеш документації з `ETag` і переклад назв методів за `Accept-Language`; кеш відповідей з ключем за реалізацією; пропускна здатність у 2.4–4.2 раза вища |
| [10.4](10.4/README-UA.md) | 10.4.4: невідоме значення enum і невалідний UUID — помилка параметра `-32602` замість внутрішньої помилки; backed enum приймає лише значення свого типу; nullable-параметри (`?Enum $x = null`) знову перетворюються. 10.4.3: знову перетворюються параметри enum / дата / uuid, DTO-результат після помилки. 10.4.2: `RpcExecutor`, точка входу для адаптерів; `rpc.post_response` для будь-якого carrier-а. 10.4.1: `IApiRouteImport` — маршрути адаптера окремо для кожної групи API. Назви й описи методів для окремої групи API (`translations/rpc/<group>.<locale>.yaml`); переклади з розширень (`translations.paths`); публічний `RpcLocaleNegotiator` |

## Оновлення з 9.x

Несумісні зміни в 10.0:

- **Інтерфейси**: `IServiceHolder::getService(string $serviceName, string $version = 'v1')`, а також нові `getServices(string $version)` і `getVersions()`; `IParamConvertor` вимагає `getParamAttr(string $classFQCN): Param`.
- **Видалені класи/методи**: `ServiceMapFactory`, `ServiceLocator`, `ServiceMap::addService()`, `ServiceMap::getTarget()`, `ServiceMap::getDescription()`. Карту сервісів будує `RpcServiceMapPass` під час компіляції.
- **Перенесено**: `Server\RequestPrepare\RpcFromCli` / `RpcFromHttp` → `Server\RequestPrepare\Holders\...`.
- **`Service`**: конструктор вимагає `Info $apiClassInfo`; `name`, `procedureFQCN`, `methodName`, `procedure` — public readonly властивості; `getResponseInfo()` повертає `RPC\DTO` замість `ResultAsDTO`. `getName()` працює в 10.x; у 10.3 позначений `#[Deprecated]` (використовуйте `$service->name`), видалення заплановане в 11.0.
- **`ApiController`**: дії перейменовано `serverAction()` → `openrpc()`, `postmanAction()` → `postman()`; нові версійні маршрути `ufo_rpc_api_server_ver`, `ufo_rpc_api_postman_ver`.
- **Змінено формат документації**: теги — аліаси процедур, помилки з `@throws`, enum — через `$ref`, змінено назву й змінні колекції Postman (`AccessToken` прибрано). Інструменти, що розбирають документи, можуть потребувати оновлення.
- **`Package`** тепер успадковує `Ufo\Packages\UfoPackage` (статичні хелпери успадковуються; нова залежність `ufo-tech/packages`).
- **Шаблон встановлення**: префікс маршруту `/api` → `/rpc` (ваш наявний файл маршрутів не змінюється).
- **Поведінка**: невалідні DTO-параметри тепер повертають помилку замість передачі сирого масиву; `RPC\Info(concat: ...)` більше не застосовується до назв методів (схоже на ненавмисну зміну). **Виправлено в [10.3.1](10.3/README-UA.md#1031).**

Наступні мінорні версії додають вимоги й менші несумісності: PHP 8.4 і `rpc-exceptions` ^2 у [10.1](10.1/README-UA.md#примітки-до-оновлення), конструктор `SimpleTokenValidator` і префікси груп у назвах маршрутів у [10.2](10.2/README-UA.md#примітки-до-оновлення), зміни конструкторів і batch у [10.3](10.3/README-UA.md#що-врахувати-при-оновленні), запасний варіант для часткового перекладу в [10.4](10.4/README-UA.md#що-врахувати-при-оновленні).

Відома проблема в 10.0.1–10.2.3: `#[Autowire]` в аргументах RPC-методів не реєструється (див. [10.0](10.0/README-UA.md#інʼєкція-сервісів-в-аргументи-rpc-методів)); використовуйте інʼєкцію через конструктор. **Виправлено в [10.3.1](10.3/README-UA.md#1031).**
