[English](README.md)

# 9.x

Мажорна версія 9 (2025-10-01 — 2026-01-20) перебудовує роботу з типами на `ufo-tech/dto-transformer` 2: типи параметрів і результатів визначаються з нативних типів і docblock в єдину JSON Schema з повноцінною підтримкою enum, union-типів і колекцій DTO — і в OpenRPC-документації, і в конвертації під час виконання. 9.1 перетворює секцію `async` на список типізованих транспортів і переносить кеш відповідей в окремий налаштовуваний пул з API зв'язків кешу та обходом кешу для окремого запиту. 9.2 робить запити з `$rpc.callback` неблокувальними і додає полегшений режим `?info` для endpoint документації.

## Вимоги

| | 9.0.0 | 9.2.1 |
|---|---|---|
| PHP | >= 8.3 | >= 8.3 |
| Symfony (`framework-bundle` і компоненти) | ^7.2 | ^7.2 |
| `ufo-tech/dto-transformer` | ^2 | ^2 |
| `ufo-tech/rpc-objects` | ^3.3 | ^3.4 |
| `ufo-tech/rpc-exceptions` | ^1.3 | ^1.3 |

## Версії

| Версія | Головне |
|---|---|
| [9.0](9.0/README-UA.md) | Система типів на dto-transformer 2: єдина JSON Schema з нативних і docblock-типів, enum, union-типи, колекції DTO, default/required для DTO в OpenRPC, рефакторинг генераторів обмежень |
| [9.1](9.1/README-UA.md) | Список типізованих async-транспортів, окремий `rpc.cache.pool` з `cache: { ttl, prefix }`, deprecated-методи в OpenRPC, API зв'язків кешу, `$rpc.ignoreCache` |
| [9.2](9.2/README-UA.md) | Неблокувальні запити з `$rpc.callback` і пересиланням заголовків, `GET ?info` для полегшеної документації |

## Оновлення з 8.x

1. Composer: `ufo-tech/dto-transformer` ^2 (було ^1.1) і `ufo-tech/rpc-objects` ^3.4 (було ^3.1). Перевірте власні атрибути `#[DTO]` / `#[ResultAsDTO]` щодо нових API (сам пакет перейшов на `#[DTOCollection(...)]` і опції через `context: [...]`).
2. Власні генератори обмежень: `Genearator` → `Generator`; `IConstraintGenerator::generate(Constraint, array &$rules, ?Generator $generator = null)`; `IsType` видалено.
3. Зміни внутрішнього API, якщо ви розширюєте пакет: видалено `Service::getParamDto()` і `Service::addReturn()`, `getParamsDto()` повертає `array<string, DtoReflector[]>`, змінено конструктори `ParamDefinition`, `JsonSchemaNormalizer`, `ChainParamConvertor`.
4. Конфігурація `async`: перепишіть як список `{type, config: {name, dsn, ...}}`; ключ `failed` більше не підтримується. OpenRPC `x-ufo.transport` використовує як ключ `name` транспорту замість `async`.
5. Додайте секцію `cache: { ttl, prefix }`; кеш відповідей тепер живе в окремому файловому пулі `rpc.cache.pool`.
6. `RpcCacheService::invalidateRelationsCache()` → `addRelationsRequestToCache()`.
7. Запити з `$rpc.callback`: миттєвий результат тепер `{"async": true, "callback": "<url>"}`; метод виконується після відповіді (для HTTP потрібен PHP-FPM), а заголовки клієнта, включно з RPC-токеном, пересилаються на callback URL.
8. У 9.2.1 OpenRPC більше не позначає методи як `deprecated`. **Виправлено в [10.3.1](../10.x/10.3/README-UA.md#1031).**
9. Після оновлення очистіть кеш Symfony.
