[English](README.md)

# 7.2 — Приховування методів через `#[RPC\IgnoreApi]`

Теги: 7.2.0. Перший реліз: 2024-07-24.

## Головне

- Новий атрибут `#[RPC\IgnoreApi]`: позначений ним публічний метод не реєструється як RPC-метод і не потрапляє в документацію.

## Що нового

### `#[RPC\IgnoreApi]`

`UfoReflectionProcedure` пропускає методи з атрибутом `Ufo\RpcObject\RPC\IgnoreApi`, а також магічні методи `__*`. До 7.2 назовні був доступний кожен публічний метод класу `IRpcService`.

**Як використати**

```php
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

final class ReportProcedure implements IRpcService
{
    public function build(int $id): array { /* доступний */ }

    #[RPC\IgnoreApi]
    public function setFormatter(Formatter $f): void { /* прихований */ }
}
```

## Зміни та виправлення

- Потрібен `ufo-tech/rpc-objects >= 2.6.0` (версія, у якій з'явився `IgnoreApi`).

## Примітки щодо оновлення

Немає. Очистіть кеш `prod`, щоб карта сервісів перебудувалася.
