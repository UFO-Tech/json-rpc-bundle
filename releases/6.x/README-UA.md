[English](README.md)

# 6.x — Symfony 7, OpenRPC, кешування й асинхронний транспорт

Вимоги (composer.json на 6.4.0): PHP `>=8.3`, Symfony `^7.0`, `ufo-tech/rpc-objects >=2.3.1`, `ufo-tech/rpc-exceptions >=1.0.3`, `psx/openrpc v0.1.1`, `phpdocumentor/reflection-docblock >=5.3`. Для асинхронного транспорту потрібен `symfony/messenger`, але пакет його не вимагає.

Мажорна версія 6 переводить пакет на Symfony 7 і PHP 8.3. API описується у форматі OpenRPC (`openrpc.json`), результати методів можна кешувати через `#[RPC\Cache]`, а запити — обробляти асинхронно через транспорт Symfony Messenger, налаштований одним DSN. Конфігурація отримала метадані проєкту й опції документації, доступні як типізовані об'єкти `ConfigService`; валідація параметрів переїхала в `ufo-tech/rpc-objects`.

| Версія | Головне |
|---|---|
| [6.4](6.4/README-UA.md) | OpenRPC на `/openrpc.json`; кеш відповідей `#[RPC\Cache]` і кешована карта сервісів; транспорт Messenger через `async.rpc_async`; конфігурація `project_*` і `docs.*`; 401/403 для помилок токена |

## Оновлення з попередньої мажорної версії

- Потрібні PHP `>=8.3` і Symfony `^7.0`; оновіть `ufo-tech/rpc-objects` до `>=2.3.1`.
- Видалено `Validations\RpcValidator` і `Interfaces\IRpcValidator` (валідатор тепер у `rpc-objects`).
- `ITokenValidator::isValid()` повертає `true`; класи безпеки приймають `RpcMainConfig`.
- Відсутні ключі конфігурації доповнюються з шаблону пакета, тому задавайте `security` явно.
- Старий документ `GET` знову змінився й позначений застарілим; використовуйте `openrpc.json`.

Деталі: [примітки до оновлення 6.4](6.4/README-UA.md#примітки-до-оновлення).
