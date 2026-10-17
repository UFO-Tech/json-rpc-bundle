[English](README.md)

# 9.1 — Кілька async-транспортів, окремий кеш відповідей, deprecated-методи

Теги: 9.1.0, 9.1.1, 9.1.2, 9.1.3, 9.1.4, 9.1.5. Перший реліз: 2025-11-17.

## Головне

- `async` тепер — список типізованих конфігурацій транспорту (`type` + `config`); можна оголосити кілька транспортів поруч (9.1.0).
- Кеш відповідей перенесено в окремий файловий пул `rpc.cache.pool`, який налаштовується новою секцією `cache: { ttl, prefix }` (9.1.1).
- Методи з `@deprecated` (docblock) або `#[\Deprecated]` позначаються як deprecated в OpenRPC (9.1.1).
- Зв'язки кешу: програмний прогрів та інвалідація через `RpcCacheService::warmCache()` / `deleteRelationCache()` (9.1.1, 9.1.5).
- Обхід кешу для окремого запиту через спеціальний параметр `$rpc.ignoreCache` (9.1.3).

## Що нового

### Кілька async-транспортів (9.1.0)

Секція `async` змінилася з фіксованої мапи (`rpc_async`, `failed`) на список. Кожен елемент має `type` і довільний `config` (`name`, `dsn`, необов'язковий `route` та будь-які додаткові ключі). Елементи з `type: amqp` реєструються як транспорти Symfony Messenger: `name` стає назвою транспорту, і `RpcAsyncRequest` (або `config.route`) маршрутизується на нього. Елементи інших типів пакет до Messenger не підключає; вони зберігаються в `RpcAsyncConfig::$rpcAsync` (доступні через `RpcAsyncConfig::getConfig(<type>)`) і, якщо `docs.async_dsn_info: true`, виводяться в OpenRPC-блоці `x-ufo.transport` під своїм `name` — розібраний DSN разом із додатковими ключами.

**Як використати:**

```yaml
# config/packages/ufo_json_rpc.yaml
ufo_json_rpc:
    async:
        - type: 'amqp'
          config:
              name: 'rpc_async'
              dsn: '%env(resolve:RPC_TRANSPORT_DSN)%'
        - type: 'mercure'
          config:
              name: 'rpc_socket'
              dsn: '%env(resolve:MERCURE_PUBLIC_URL)%'
              topics_prefix: 'rpc.event.'
```

Стара мапа з одним елементом `async: { rpc_async: '<dsn>' }` досі нормалізується в один `amqp`-елемент, але береться лише її перший ключ.

### Окремий пул кешу і секція `cache` (9.1.1)

`RpcCacheService` тепер використовує власний сервіс `rpc.cache.pool` (`FilesystemAdapter` у `%kernel.cache_dir%`) замість автоматично підставленого кешу застосунку. Namespace і час життя за замовчуванням беруться з нової секції конфігурації.

**Як використати:**

```yaml
ufo_json_rpc:
    cache:
        ttl: 60              # час життя за замовчуванням, секунди
        prefix: 'rpc_cache'  # namespace кешу
```

Ключ кешу тепер рахується з рекурсивно відсортованих параметрів (`sortRecursive()`), тож порядок ключів у вкладених об'єктах не змінює ключ. Зверніть увагу: списки теж сортуються.

### Deprecated-методи в OpenRPC (9.1.1)

`DescriptionFiller` позначає метод як deprecated, якщо в docblock є `@deprecated`; `AttributesFiller` робить те саме для PHP-атрибута `#[\Deprecated]` (нативний з PHP 8.4). `OpenRpcSpecBuilder::buildMethod()` записує `deprecated: true` для таких методів.

**Як використати:**

```php
/**
 * @deprecated use Orders.create
 */
public function createOrder(array $items): array { /* ... */ }
```

### API зв'язків кешу (9.1.1, 9.1.5)

`RpcCacheService` отримав публічні методи для сценаріїв з `#[RPC\CacheRelation]`:

- `warmCache(string $class, array $methods, array $params, bool $deleteCache = true)` — будує пов'язані запити для вказаних методів процедури, за потреби видаляє їхні кешовані відповіді й виконує їх знову, щоб наповнити кеш (9.1.1).
- `deleteRelationCache(string $class, array $methods, array $params)` — лише видаляє кешовані відповіді пов'язаних запитів (9.1.5).
- `deleteRequestCache(RpcRequest $request)` — видаляє кешовану відповідь одного запиту (9.1.5).
- `invalidateRelationsCache()` перейменовано на `addRelationsRequestToCache(array $params, string $id, bool $deleteCache = true)`.

**Як використати:**

```php
public function __construct(private RpcCacheService $rpcCache) {}

// після оновлення сутності
$this->rpcCache->deleteRelationCache(OrderProcedure::class, ['get', 'list'], ['id' => $id]);
```

### Спеціальний параметр `$rpc.ignoreCache` (9.1.3)

`RpcServer::handle()` пропускає кешовану відповідь, якщо спеціальні параметри запиту містять `ignoreCache: true`; відповідь обчислюється і зберігається в кеш заново. Потрібен `ufo-tech/rpc-objects` ^3.4. Запити прогріву, які будує API зв'язків кешу, ставлять цей прапорець автоматично.

**Як використати:**

```json
{"id": 1, "method": "Orders.get", "params": {"id": 10, "$rpc": {"ignoreCache": true}}}
```

## Зміни та виправлення

- 9.1.1 — OpenRPC `x-ufo.documentation` містить посилання `postman` (`<server url>/postman`). `RpcAsyncProcessor::processAsync(RpcRequest)` винесено з `__invoke()` (перевірка токена лишилася в `__invoke()`). `RpcEventFactory::fire()` приймає як назву події, так і об'єкт події. `ParamsConverterEventListener` знову передає значення `null` у конвертер параметрів (9.0.6 пропускав їх як нескалярні). Конфігураційні файли переформатовано на YAML з відступом 2 пробіли. `RpcServer::handle()` отримав аргумент `ignoreCache` (замінено в 9.1.3).
- 9.1.2 — Виправлено `warmCache()`, коли рефлексія процедури ще не була закешована (змінна рефлексії не присвоювалася).
- 9.1.3 — `ufo-tech/rpc-objects` ^3.4; `RpcServer::handle(RpcRequest $singleRequest)` втратив аргумент `ignoreCache` на користь `$rpc.ignoreCache`.
- 9.1.4 — `RequestCarrier` реалізує розширений `IRpcSpecialParamHandler` (`setParam()`, `resetParams()`); спеціальні параметри вхідного запиту розбираються і валідуються під час встановлення carrier.
- 9.1.5 — Додано `deleteRelationCache()` і `deleteRequestCache()`; перебір зв'язків відрефакторено; застарілий `invalidateRelationsCache()` видалено.

## Примітки до оновлення

- Конфігурація `async`: перепишіть у формі списку, як вище. Ключ `failed` більше не підтримується; якщо потрібен транспорт для невдалих повідомлень, оголосіть його в `framework.messenger` самостійно. Код, що читає `RpcAsyncConfig::$rpcAsync`, отримує масив `RpcAsyncInfo` замість рядка DSN, а `RpcAsyncConfig::$failed` зник. В OpenRPC `x-ufo.transport` async-транспорти мають ключ за своїм `name` (напр. `rpc_async`) замість `async`.
- Додайте секцію `cache:` (як в `install/packages/ufo_json_rpc.yaml`): `rpc.cache.pool` читає параметри `ufo_json_rpc.cache.ttl` / `ufo_json_rpc.cache.prefix`, які визначаються лише за наявності секції. Відповіді, закешовані в старому пулі застосунку, не використовуються.
- `RpcCacheService::invalidateRelationsCache()` → `addRelationsRequestToCache()` (видалено в 9.1.5).
- З 9.1.3 потрібен `ufo-tech/rpc-objects` ^3.4.
