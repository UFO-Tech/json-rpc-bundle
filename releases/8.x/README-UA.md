[English](README.md)

# 8.x

Мажорна версія 8 перебудовує обробку запитів навколо подій Symfony: RPC-ендпоінт обробляє listener `kernel.request`, `ufo:rpc:process` — listener `console.command`, а події життєвого циклу RPC (`rpc.pre_execute`, `rpc.post_response`, `rpc.error` …) тепер належать бандлу. HTTP-, CLI- та async-виклики мають одну модель безпеки на основі token holders, а конвертори параметрів перетворюють скалярні значення на об'єкти `BackedEnum`, `DateTime*` та UUID. Версія 8.1 будує документацію OpenRPC за типами з PHPDoc (`ItemDTO[]`, `array<…>`, union), гідрує колекції DTO, передані як параметри, і публікує значення enum у схемах.

## Вимоги

| | 8.0.0 | 8.1.14 |
|---|---|---|
| PHP | >= 8.3 | >= 8.3 |
| Symfony | ^7.2 | ^7.2 |
| ufo-tech/dto-transformer | ^1.0 | ^1.1 |
| ufo-tech/rpc-objects | ^3.0 | ^3.1 |
| ufo-tech/rpc-exceptions | ^1.1 | ^1.3 |

Нові у 8.0: `symfony/yaml`, `symfony/uid`, `ramsey/uuid` ^4.

## Версії

| Версія | Головне |
|---|---|
| [8.0](8.0/README-UA.md) | Подієва обробка HTTP/CLI; події RPC у бандлі, зокрема `rpc.post_response`; конвертори параметрів (enum, DateTime, UUID); єдина токен-безпека для HTTP/CLI/async; кеш опису кожного сервісу |
| [8.1](8.1/README-UA.md) | Схеми OpenRPC з PHPDoc; колекції DTO як параметри; схеми enum для параметрів, властивостей DTO, масивів і union |

## Оновлення з 7.x

- Класи подій перенесено з `Ufo\RpcObject\Events\*` до `Ufo\JsonRpcBundle\EventDrivenModel\Events\*`.
- POST-запити більше не обробляє `ApiController` (він віддає лише `GET`-документацію); `RpcRequestHandler::handle()` не приймає `Request`.
- API `IRpcSecurity` замінено (`setTokenHolder()`, `getTokenHolder()`, `isValidApiRequest()`, `isValidDocRequest()`); видалено `TokenRpcCliSecurity`, `Security\Helper`, `SecurityListener`.
- `RpcSecurityConfig::$tokenKeyInHeader` → `$tokenName`, `TOKEN_KEY` → `TOKEN_NAME` (YAML-ключ `token_name` не змінився).
- `ufo:rpc:process`: `-t/--token` → `--<token_name у нижньому регістрі>` (за замовчуванням `--ufo-rpc-token`).
- Видалено: `CurrentRpcRequestHolder`, `RpcRequestHelper`, `CliRpcListener`, `DTOToParamsEventListener`, `ServiceMapFactory::getServiceLocator()`; `StopHandlerException` → `StopHandler`.
- Відсутній токен → `RpcTokenNotSentException` (HTTP 401).
- Змінився ключ кешу service map — очистіть кеш після оновлення.
- Деталі: [що врахувати при оновленні до 8.0](8.0/README-UA.md#що-врахувати-при-оновленні).
