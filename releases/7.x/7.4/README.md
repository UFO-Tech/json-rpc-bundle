[Українська](README-UA.md)

# 7.4 — Method locks, DTO parameters, pluggable service fillers

Tags: 7.4.0, 7.4.1, 7.4.2. First released: 2025-03-14.

## Highlights

- `#[RPC\Lock]`: blocks concurrent calls of a method, optionally per parameter value, using Symfony Lock on top of the app cache.
- DTO parameters: a parameter typed with a class is automatically hydrated from the incoming JSON object.
- Service metadata is collected by a chain of tagged "fillers" (`ufo-rpc.service-iterator`), so you can register your own.
- `CurrentRpcRequestHolder` gives access to the RPC request being processed and exposes its special params, including `rayId` (the parent request id).

## What's new

### Method locking with `#[RPC\Lock]`

`LockEventListener` (on `rpc.pre_execute`, priority -100) acquires a lock through `LockerService` before the method runs. `ServerEventListener` releases it in a `finally` block. The lock key is `md5(method name + value of paramName)`. If the lock is held, the call fails with `RpcLogicException`: "Method '<name>' is locked." Locks are stored by `UfoLockerStore`, which keeps lock keys (`lock_<key>`) in the autowired Symfony cache (`CacheInterface`).

**How to use**

```php
use Ufo\RpcObject\RPC;

#[RPC\Lock(paramName: 'orderId', ttl: RPC\Lock::T_10_SECOND)]
public function pay(string $orderId): bool { /* ... */ }
```

Without `paramName`, the whole method is locked regardless of its arguments.

### DTO parameters

`ParamFiller` registers every parameter whose type is an existing class as a DTO parameter. Before execution, `DTOToParamsEventListener` (`rpc.pre_execute`, priority 1000) converts the incoming array to that class with `Ufo\RpcObject\DTO\DTOTransformer::fromArray()`.

**How to use**

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

### Service fillers

`UfoReflectionProcedure` now delegates to `ChainServiceFiller`, which iterates over all services tagged `ufo-rpc.service-iterator`. The built-in fillers are `DescriptionFiller`, `ParamFiller` (priority 100), `ReturnFiller`, `ThrowsFiller`, `ResponseFiller`, `AssertionsFiller`, `CacheFiller` and `LockFiller`. Each one implements `IServiceFiller::fill(ReflectionMethod, Service, DocBlock)`. Extending `AbstractServiceFiller` tags a class automatically.

**How to use**

```php
use phpDocumentor\Reflection\DocBlock;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers\AbstractServiceFiller;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;

final class MyFiller extends AbstractServiceFiller
{
    public function fill(\ReflectionMethod $method, Service $service, DocBlock $methodDoc): void
    {
        // enrich $service from your own attributes
    }
}
```

## Changes and fixes

- 7.4.1: registered `UfoLockerStore` as a service (fixes autowiring) and added the missing `symfony/lock` requirement.
- 7.4.2: `ParamFiller` now runs first (priority 100).
- `RpcCacheService::hashArrayData()` is now public.

## Upgrade notes

- New requirements: `ext-sysvsem`, `symfony/lock ^7.0` and `ufo-tech/rpc-objects ^2.8`.
- `Server\ServiceMap\Reflections\ResultAsDtoReflector` was renamed to `DtoReflector` and now accepts any `Ufo\RpcObject\RPC\DTO`.
- Parameters typed with a class now receive a hydrated object instead of failing with a type error. If you relied on that, review such signatures.
- Clear the `prod` cache: the cached `Service` structure changed.
