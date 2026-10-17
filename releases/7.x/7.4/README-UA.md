[English](README.md)

# 7.4 — Блокування методів, DTO-параметри, підключувані filler'и сервісів

Теги: 7.4.0, 7.4.1, 7.4.2. Перший реліз: 2025-03-14.

## Головне

- `#[RPC\Lock]`: блокує одночасні виклики методу, за потреби окремо для кожного значення параметра. Працює на Symfony Lock поверх кешу застосунку.
- DTO-параметри: параметр із типом-класом автоматично гідратується з вхідного JSON-об'єкта.
- Метадані сервісів збирає ланцюжок тегованих «filler'ів» (`ufo-rpc.service-iterator`), тож можна зареєструвати власний.
- `CurrentRpcRequestHolder` дає доступ до RPC-запиту, що обробляється, і віддає його спеціальні параметри, зокрема `rayId` (id батьківського запиту).

## Що нового

### Блокування методу через `#[RPC\Lock]`

`LockEventListener` (на `rpc.pre_execute`, пріоритет -100) захоплює lock через `LockerService` до виконання методу. `ServerEventListener` звільняє його в блоці `finally`. Ключ блокування — `md5(назва методу + значення paramName)`. Якщо lock зайнятий, виклик завершується `RpcLogicException`: «Method '<name>' is locked.» Locks зберігає `UfoLockerStore`, який тримає ключі (`lock_<key>`) в autowired Symfony cache (`CacheInterface`).

**Як використати**

```php
use Ufo\RpcObject\RPC;

#[RPC\Lock(paramName: 'orderId', ttl: RPC\Lock::T_10_SECOND)]
public function pay(string $orderId): bool { /* ... */ }
```

Без `paramName` метод блокується цілком, незалежно від аргументів.

### DTO-параметри

`ParamFiller` реєструє як DTO-параметр кожен параметр, тип якого — наявний клас. Перед виконанням `DTOToParamsEventListener` (`rpc.pre_execute`, пріоритет 1000) перетворює вхідний масив на цей клас через `Ufo\RpcObject\DTO\DTOTransformer::fromArray()`.

**Як використати**

```php
final class CreateOrderDTO
{
    public string $sku;
    public int $qty;
}

public function create(CreateOrderDTO $order): int { /* ... */ }
```

```json
{"jsonrpc": "2.0", "id": 1, "method": "Order.create", "params": {"order": {"sku": "A-1", "qty": 2}}}
```

### Filler'и сервісів

`UfoReflectionProcedure` тепер делегує роботу `ChainServiceFiller`, який проходить усі сервіси з тегом `ufo-rpc.service-iterator`. Вбудовані filler'и: `DescriptionFiller`, `ParamFiller` (пріоритет 100), `ReturnFiller`, `ThrowsFiller`, `ResponseFiller`, `AssertionsFiller`, `CacheFiller` і `LockFiller`. Кожен реалізує `IServiceFiller::fill(ReflectionMethod, Service, DocBlock)`. Клас, що наслідує `AbstractServiceFiller`, тегується автоматично.

**Як використати**

```php
use phpDocumentor\Reflection\DocBlock;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers\AbstractServiceFiller;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;

final class MyFiller extends AbstractServiceFiller
{
    public function fill(\ReflectionMethod $method, Service $service, DocBlock $methodDoc): void
    {
        // доповніть $service з власних атрибутів
    }
}
```

## Зміни та виправлення

- 7.4.1: `UfoLockerStore` зареєстровано як сервіс (виправлено autowiring) і додано відсутню залежність `symfony/lock`.
- 7.4.2: `ParamFiller` тепер виконується першим (пріоритет 100).
- `RpcCacheService::hashArrayData()` тепер публічний.

## Примітки щодо оновлення

- Нові залежності: `ext-sysvsem`, `symfony/lock ^7.0` і `ufo-tech/rpc-objects ^2.8`.
- `Server\ServiceMap\Reflections\ResultAsDtoReflector` перейменовано на `DtoReflector`; тепер він приймає будь-який `Ufo\RpcObject\RPC\DTO`.
- Параметри з типом-класом тепер отримують гідратований об'єкт, а не падають з помилкою типу. Якщо ви на це покладалися, перевірте такі сигнатури.
- Очистіть кеш `prod`: змінилася структура закешованого `Service`.
