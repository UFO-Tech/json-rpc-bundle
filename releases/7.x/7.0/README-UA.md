[English](README.md)

# 7.0 — Подієве ядро та нативний OpenRPC

Теги: 7.0.0. Перший реліз: 2024-07-12.

## Головне

- Обробку запиту перебудовано на подіях: запит, валідація, виконання, відповідь і помилки обробляють окремі listeners, до яких можна під'єднатися.
- `GET` на API endpoint тепер повертає документ OpenRPC з `components/schemas` для DTO відповідей і `x-ufo-assertions` у параметрах.
- Конфіг `ufo_json_rpc` спрощено: два булеві перемикачі безпеки, а інформація про проєкт переїхала в `docs`.
- Карту сервісів будує `ServiceMapFactory`, у `prod` вона кешується.
- Шар фасадів (`RpcServerFacade`, `IFacadeRpcServer`) видалено.

## Що нового

### Подієвий життєвий цикл запиту

`RpcServer` більше не викликає процедури напряму. Він генерує події через `RpcEventFactory`, а обробляють їх власні listeners бандла з `Ufo\JsonRpcBundle\EventDrivenModel\Listeners`. `ValidateParamsListener` (пріоритет 1001/1000) валідує параметри, `ServerEventListener` (пріоритет -1000) виконує метод і формує відповідь, `RpcErrorListener` перетворює винятки kernel на помилки JSON-RPC, а `LoggerListener` логує `rpc.error`. Класи подій і константи `RpcEvent` (`REQUEST`, `REQUEST_ASYNC`, `OUTPUT_ASYNC`, `PRE_EXECUTE`, `POST_EXECUTE`, `RESPONSE`, `ERROR`) лежать в `ufo-tech/rpc-objects` (`Ufo\RpcObject\Events`).

**Як використати.** Зареєструйте звичайний Symfony listener. Щоб він спрацьовував після валідації і до виконання, оберіть пріоритет між вбудованими:

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Ufo\RpcObject\Events\RpcEvent;
use Ufo\RpcObject\Events\RpcPreExecuteEvent;

#[AsEventListener(RpcEvent::PRE_EXECUTE, 'onPreExecute', priority: 500)]
final class AuditListener
{
    public function onPreExecute(RpcPreExecuteEvent $event): void
    {
        // $event->rpcRequest, $event->service, $event->params
    }
}
```

### OpenRPC з основного endpoint

`ApiController::serverAction` обробляє `POST` як RPC-виклик, а `GET` — як `OpenRpcAdapter::adapt()`. Документ тепер містить:
- `components/schemas`, побудовані з DTO-інформації `#[RPC\Response]`
- `x-ufo-assertions` у кожному параметрі — код його Symfony constraints
- транспорти в `x-ufo.transport` об'єкта server
- посилання на документацію

**Як використати.**

```bash
curl https://example.test/api          # OpenRPC JSON (префікс /api з install/routes)
```

Щоб і цей `GET` вимагав токен, встановіть `security.protected_doc: true`.

### Спрощена конфігурація

```yaml
# config/packages/ufo_json_rpc.yaml
ufo_json_rpc:
    security:
        protected_api: true          # токен для POST (RPC-виклики)
        protected_doc: false         # токен для GET (документація)
        token_name: 'Ufo-RPC-Token'  # назва заголовка
        clients_tokens:
            - '%env(resolve:UFO_API_TOKEN)%'
    docs:
        project_name: 'My Project'
        project_description: ''
        project_version: null
        async_dsn_info: false
        validations:
            symfony_asserts: false
    async:
        # rpc_async: '%env(resolve:RPC_TRANSPORT_DSN)%'
        # failed: ...
```

## Зміни та виправлення

- Нові `ServiceMap` / `ServiceMapFactory`. У `prod` карта сервісів кешується під ключем `rps.service_map` з TTL один рік. В інших середовищах вона перебудовується на кожен запит.
- `RpcCacheService` тепер працює з `Psr\Cache\CacheItemPoolInterface` і більше не реалізує `CacheInterface`.
- `SecurityListener` перевіряє токен на `kernel.request`. `RpcErrorListener` повертає HTTP 401, якщо заголовка немає, і 403, якщо токен невалідний.
- `IRpcSecurity` — аліас на `TokenRpcSecurity`. Async-обробник і команда `ufo:rpc:process` напряму використовують `TokenRpcCliSecurity`.
- Власний `TypeHintResolver` бандла видалено, замість нього використовується той, що в `Ufo\RpcObject\Helpers`.
- Потрібен `ufo-tech/rpc-objects >= 2.4.0`.

## Примітки щодо оновлення

- **Ключі конфігу змінено.** Дерево оголошене з `ignoreExtraKeys(false)`, тому старі ключі **не викликають помилки, а мовчки ігноруються**. Перенесіть їх вручну:
  - `project_name`, `project_description`, `project_version` → `docs.*`
  - `security.protected_methods: ['POST', 'GET']` → `security.protected_api` / `security.protected_doc` (булеві; типові значення `true` / `false`)
  - `security.token_key_in_header` → `security.token_name`
  - `docs.response.async_dsn_info` → `docs.async_dsn_info`. `docs.response.validations.symfony_asserts` → `docs.validations.symfony_asserts`
  - `docs.response.key_for_methods` і `docs.response.validations.json_schema` видалено. JSON Schema генерується завжди.
- **Видалено маршрути** `GET /method/{method}` і `GET /openrpc.json`. Використовуйте `GET` на основний endpoint — тепер він повертає OpenRPC замість старого масиву карти сервісів.
- **Видалено класи** `Server\RpcServerFacade`, `Server\RpcServerFacadeCli`, `Interfaces\IFacadeRpcServer`, `Server\ServiceMap\TypeHintResolver`.
- **Listeners перенесено:** `EventListener\HandleExceptionListener` → `EventDrivenModel\Listeners\RpcErrorListener`, `EventListener\HandleCliListener` → `EventDrivenModel\Listeners\CliRpcListener`. Оновіть код, який інжектив або декорував фасади.
- Після деплою в `prod` очистіть кеш застосунку, щоб карта сервісів перебудувалася.
