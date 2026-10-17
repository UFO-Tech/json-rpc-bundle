[English](README.md)

# ufo-tech/json-rpc-bundle 7.x

Мажорна версія 7 перебудувала бандл навколо подієвого конвеєра обробки запиту. Валідація, виконання, блокування, кешування та обробка помилок тепер є Symfony event listeners, які можна розширювати. Шар фасадів прибрано, а `GET` на API endpoint віддає нативний документ OpenRPC. Протягом лінійки 7.x бандл отримав:
- схеми результатів на основі DTO (`#[RPC\ResultAsDTO]`)
- приховування методів через `#[RPC\IgnoreApi]`
- експорт колекції Postman і ширше перетворення Symfony constraints → JSON Schema
- блокування методів (`#[RPC\Lock]`) і автоматичну гідратацію DTO-параметрів
- підключувані filler'и метаданих сервісів
- інвалідацію та прогрів пов'язаного кешу (`#[RPC\CacheRelation]`) з новими подіями pre/post-response

## Вимоги

| | 7.0.0 | 7.6.0 |
|---|---|---|
| PHP | `>=8.3` | `>=8.3` |
| Symfony (`framework-bundle` та інші `symfony/*`) | `^7.0` | `^7.2` |
| `ufo-tech/rpc-objects` | `>=2.4.0` | `^3.0` |
| Інше | `ext-dom`, `psx/openrpc v0.1.1` | додатково `ext-sysvsem`, `composer-runtime-api`, `symfony/lock ^7.2`, `ufo-tech/dto-transformer ^1.0` |

## Версії

| Версія | Головне |
|---|---|
| [7.0](7.0/README-UA.md) | Подієве ядро; OpenRPC на `GET` endpoint; спрощений конфіг (`protected_api` / `protected_doc`, `docs.project_*`) |
| [7.1](7.1/README-UA.md) | `#[RPC\ResultAsDTO]` зі схемами вкладених DTO / колекцій; інформація про середовище в OpenRPC |
| [7.2](7.2/README-UA.md) | `#[RPC\IgnoreApi]` для приховування публічних методів |
| [7.3](7.3/README-UA.md) | Експорт колекції Postman (`GET /postman`); більше constraints у JSON Schema; виправлення перевірки токена в async; дочірні процедури перевизначають батьківські |
| [7.4](7.4/README-UA.md) | `#[RPC\Lock]`; автоматична гідратація DTO-параметрів; теговані filler'и сервісів; `CurrentRpcRequestHolder` |
| [7.5](7.5/README-UA.md) | Базова Symfony 7.2; виправлення перевірки токена в CLI та async batch; прибрано deprecations |
| [7.6](7.6/README-UA.md) | Інвалідація та прогрів `#[RPC\CacheRelation]`; `rpc.pre_response` / `rpc.post_response`; універсальна колекція атрибутів; rpc-objects 3 + dto-transformer |

## Оновлення з 6.x

1. **Перепишіть `config/packages/ufo_json_rpc.yaml`.** Невідомі ключі мовчки ігноруються, а не відхиляються.
   - `project_name`, `project_description`, `project_version` → `docs.project_*`
   - `security.protected_methods` → `security.protected_api` (POST) / `security.protected_doc` (GET), обидва булеві
   - `security.token_key_in_header` → `security.token_name`
   - `docs.response.*` перенесено напряму в `docs.*`. `key_for_methods` і `validations.json_schema` видалено.
2. **Маршрути:** `/method/{method}` і `/openrpc.json` видалено. `GET` на API endpoint повертає OpenRPC замість старої карти сервісів.
3. **Видалені класи:** `RpcServerFacade`, `RpcServerFacadeCli`, `IFacadeRpcServer`, `Server\ServiceMap\TypeHintResolver`. Класи `EventListener\*` перенесено в `EventDrivenModel\Listeners\*`. Починаючи з 7.1, `Serializer\RpcErrorNormalizer` і `Serializer\RpcResponseContextBuilder` лежать в `Ufo\RpcObject\Transformer`.
4. **Залежності:** `ufo-tech/rpc-objects` 2.4+ для 7.0 і `^3.0` для 7.6. З 7.4 потрібні `ext-sysvsem` і `symfony/lock`. З 7.5.1 — Symfony 7.2+.
5. **Поведінка:** з 7.3 async-повідомлення (Messenger) перевіряються на токен, якщо `protected_api` увімкнено. З 7.6 `#[RPC\Response]` і `RpcEvent::RESPONSE` більше не використовуються (див. [7.6](7.6/README-UA.md)).
6. Очищайте кеш застосунку після кожного оновлення. Карта сервісів кешується в `prod` (`rps.service_map`), і її формат кілька разів змінювався протягом 7.x.
