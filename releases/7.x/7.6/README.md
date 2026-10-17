[Українська](README-UA.md)

# 7.6 — Related cache invalidation and response lifecycle events

Tags: 7.6.0. First released: 2025-05-15.

## Highlights

- `#[RPC\CacheRelation]`: a successful call drops the cached responses of related methods and can optionally warm them up again.
- New response events: `rpc.pre_response` (when the response is built) and `rpc.post_response` (on `kernel.terminate`, after the response is sent).
- Method attributes are stored in a generic `ServiceAttributesCollection` instead of dedicated fields for each attribute.
- Moved to `ufo-tech/rpc-objects ^3.0` and the new `ufo-tech/dto-transformer ^1.0`.
- OpenRPC: union return types are rendered properly, and `x-ufo.environment.fromCache` shows whether the service map came from cache.

## What's new

### `#[RPC\CacheRelation]`

`CacheRelationsEventListener` handles `rpc.post_response`, which fires on `kernel.terminate` after the response has been sent. For each `CacheRelation` on the executed method, it:
1. Resolves the listed `methods` of `serviceFQCN`. If `serviceFQCN` is omitted, the current procedure class is used.
2. Deletes their cached responses. The cache key is built from the related method's name and the subset of the current request's params whose names match that method's parameters.
3. If `warmUp: true`, re-executes those methods with the same params so the cache is filled again.

**How to use**

```php
use Ufo\RpcObject\RPC;

#[RPC\Info(alias: 'User')]
final class UserProcedure implements IRpcService
{
    #[RPC\Cache(lifetimeSecond: RPC\Cache::T_HOUR)]
    public function get(int $id): UserDTO { /* ... */ }

    #[RPC\CacheRelation(methods: ['get'], warmUp: true)]
    public function rename(int $id, string $name): bool { /* ... */ }
}
```

After `User.rename {"id": 5, ...}` succeeds, the cached `User.get {"id": 5}` is dropped and rebuilt. Only the last single request handled in the HTTP request is tracked.

### Response lifecycle events

`ServerEventListener` now fires `RpcEvent::PRE_RESPONSE` (with the response, request and service) for both results and errors. It fires `RpcEvent::POST_RESPONSE` on `kernel.terminate`. `RpcEvent::POST_EXECUTE` now fires after the response object is built and carries the result, request, service and params.

**How to use**

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Ufo\RpcObject\Events\RpcEvent;
use Ufo\RpcObject\Events\RpcPostResponseEvent;

#[AsEventListener(RpcEvent::POST_RESPONSE, 'onSent')]
final class MetricsListener
{
    public function onSent(RpcPostResponseEvent $event): void
    {
        // $event->response, $event->rpcRequest, $event->service — runs after the client got the response
    }
}
```

### Generic method attributes

`AttributesFiller` (which replaces `CacheFiller` and `LockFiller`) stores every attribute instance of a method in `Service::getAttrCollection()`. Read them with `getAttribute(Cache::class)` / `getAttributes(CacheRelation::class)`. The collection survives the `prod` service map cache.

## Changes and fixes

- `ServiceLocator::get()` throws `ServiceNotFoundException` if the located service is not an `IRpcService`. `has()` uses the same check.
- The response-cache key hash changed from `sha256` to `md5` (`RpcCacheService::getCacheKey()`).
- Fixed OpenRPC result schemas for multiple (union) return types. Fixed DTO handling with `dto-transformer`.
- `x-ufo-assertions` is now stored on the service (`Service::getUfoAssertion()`) and kept in the cache.
- `dto-transformer` was added to the package versions reported in `x-ufo.environment`.

## Upgrade notes

- **Dependencies:** `ufo-tech/rpc-objects ^3.0` and `ufo-tech/dto-transformer ^1.0` (new). `TypeHintResolver` and `DTOTransformer` now come from `Ufo\DTO\...` (previously `Ufo\RpcObject\Helpers\TypeHintResolver` and `Ufo\RpcObject\DTO\DTOTransformer`).
- **`#[RPC\Response]` is no longer read.** Use `#[RPC\ResultAsDTO(Dto::class, collection: ...)]`.
- **`RpcEvent::RESPONSE` is no longer fired by the bundle.** Move listeners to `RpcEvent::PRE_RESPONSE` (the response is in the event and in `$event->rpcRequest->getResponseObject()`) or to `RpcEvent::POST_RESPONSE`.
- **`RpcEvent::POST_EXECUTE`** is now fired after the response is created, with extra arguments.
- **`Service` API:** `getCacheInfo()` / `setCacheInfo()`, `getLockInfo()` / `setLockInfo()` were removed; use `getAttrCollection()->getAttribute(Cache::class | Lock::class)`. `Service::fromArray()` now has the signature `fromArray(array $data, array $renameKey = [])` and is backed by `Ufo\DTO\ServiceTransformer`.
- **Fillers:** `CacheFiller` was renamed to `AttributesFiller`, and `LockFiller` was removed.
- Existing response-cache entries are no longer found because of the new hash. Clear the cache, including the service map, after deploying.
