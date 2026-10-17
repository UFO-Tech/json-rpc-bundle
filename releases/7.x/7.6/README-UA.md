[English](README.md)

# 7.6 — Інвалідація пов'язаного кешу та події життєвого циклу відповіді

Теги: 7.6.0. Перший реліз: 2025-05-15.

## Головне

- `#[RPC\CacheRelation]`: успішний виклик скидає закешовані відповіді пов'язаних методів і за потреби одразу прогріває їх знову.
- Нові події відповіді: `rpc.pre_response` (коли відповідь сформовано) і `rpc.post_response` (на `kernel.terminate`, після відправлення відповіді).
- Атрибути методів зберігаються в універсальній `ServiceAttributesCollection` замість окремого поля для кожного атрибута.
- Перехід на `ufo-tech/rpc-objects ^3.0` і новий `ufo-tech/dto-transformer ^1.0`.
- OpenRPC: union-типи повернення відображаються правильно, а `x-ufo.environment.fromCache` показує, чи карту сервісів взято з кешу.

## Що нового

### `#[RPC\CacheRelation]`

`CacheRelationsEventListener` обробляє `rpc.post_response`, яка спрацьовує на `kernel.terminate` після відправлення відповіді. Для кожного `CacheRelation` виконаного методу він:
1. Знаходить перелічені `methods` класу `serviceFQCN`. Якщо `serviceFQCN` не вказано, береться поточний клас процедури.
2. Видаляє їхні закешовані відповіді. Ключ кешу будується з назви пов'язаного методу та тих параметрів поточного запиту, чиї назви збігаються з параметрами цього методу.
3. Якщо `warmUp: true`, повторно виконує ці методи з тими самими параметрами, щоб кеш знову заповнився.

**Як використати**

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

Після успішного `User.rename {"id": 5, ...}` закешований `User.get {"id": 5}` скидається і будується заново. Відстежується лише останній одиночний запит, оброблений у межах HTTP-запиту.

### Події життєвого циклу відповіді

`ServerEventListener` тепер генерує `RpcEvent::PRE_RESPONSE` (з відповіддю, запитом і сервісом) як для результатів, так і для помилок. `RpcEvent::POST_RESPONSE` він генерує на `kernel.terminate`. `RpcEvent::POST_EXECUTE` тепер спрацьовує після створення об'єкта відповіді й передає результат, запит, сервіс і параметри.

**Як використати**

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Ufo\RpcObject\Events\RpcEvent;
use Ufo\RpcObject\Events\RpcPostResponseEvent;

#[AsEventListener(RpcEvent::POST_RESPONSE, 'onSent')]
final class MetricsListener
{
    public function onSent(RpcPostResponseEvent $event): void
    {
        // $event->response, $event->rpcRequest, $event->service — виконується після того, як клієнт отримав відповідь
    }
}
```

### Універсальні атрибути методів

`AttributesFiller` (замінює `CacheFiller` і `LockFiller`) зберігає всі екземпляри атрибутів методу в `Service::getAttrCollection()`. Читати їх можна через `getAttribute(Cache::class)` / `getAttributes(CacheRelation::class)`. Колекція зберігається і в кеші карти сервісів у `prod`.

## Зміни та виправлення

- `ServiceLocator::get()` викидає `ServiceNotFoundException`, якщо знайдений сервіс не є `IRpcService`. `has()` використовує ту саму перевірку.
- Хеш ключа кешу відповідей змінено з `sha256` на `md5` (`RpcCacheService::getCacheKey()`).
- Виправлено схеми результату OpenRPC для кількох (union) типів повернення. Виправлено роботу з DTO через `dto-transformer`.
- `x-ufo-assertions` тепер зберігається в сервісі (`Service::getUfoAssertion()`) і потрапляє в кеш.
- `dto-transformer` додано до версій пакетів, які показує `x-ufo.environment`.

## Примітки щодо оновлення

- **Залежності:** `ufo-tech/rpc-objects ^3.0` і `ufo-tech/dto-transformer ^1.0` (новий). `TypeHintResolver` і `DTOTransformer` тепер беруться з `Ufo\DTO\...` (раніше — `Ufo\RpcObject\Helpers\TypeHintResolver` і `Ufo\RpcObject\DTO\DTOTransformer`).
- **`#[RPC\Response]` більше не зчитується.** Використовуйте `#[RPC\ResultAsDTO(Dto::class, collection: ...)]`.
- **`RpcEvent::RESPONSE` бандл більше не генерує.** Перенесіть listeners на `RpcEvent::PRE_RESPONSE` (відповідь є в події та в `$event->rpcRequest->getResponseObject()`) або на `RpcEvent::POST_RESPONSE`.
- **`RpcEvent::POST_EXECUTE`** тепер генерується після створення відповіді й має додаткові аргументи.
- **API `Service`:** видалено `getCacheInfo()` / `setCacheInfo()` і `getLockInfo()` / `setLockInfo()`; використовуйте `getAttrCollection()->getAttribute(Cache::class | Lock::class)`. `Service::fromArray()` тепер має сигнатуру `fromArray(array $data, array $renameKey = [])` і працює через `Ufo\DTO\ServiceTransformer`.
- **Filler'и:** `CacheFiller` перейменовано на `AttributesFiller`, `LockFiller` видалено.
- Через новий хеш наявні записи кешу відповідей більше не знаходяться. Після деплою очистіть кеш, зокрема карту сервісів.
