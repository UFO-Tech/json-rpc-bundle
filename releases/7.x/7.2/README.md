[Українська](README-UA.md)

# 7.2 — Hide methods with `#[RPC\IgnoreApi]`

Tags: 7.2.0. First released: 2024-07-24.

## Highlights

- New `#[RPC\IgnoreApi]` attribute: a public method marked with it is not registered as an RPC method and does not appear in the docs.

## What's new

### `#[RPC\IgnoreApi]`

`UfoReflectionProcedure` skips methods that carry `Ufo\RpcObject\RPC\IgnoreApi`, as well as magic `__*` methods. Before 7.2, every public method of an `IRpcService` class was exposed.

**How to use**

```php
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

final class ReportProcedure implements IRpcService
{
    public function build(int $id): array { /* exposed */ }

    #[RPC\IgnoreApi]
    public function setFormatter(Formatter $f): void { /* not exposed */ }
}
```

## Changes and fixes

- Requires `ufo-tech/rpc-objects >= 2.6.0` (the version that ships `IgnoreApi`).

## Upgrade notes

None. Clear the `prod` cache so the service map is rebuilt.
