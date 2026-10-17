[English](README.md)

# 4.5 — JSON-RPC 2.0 сервер для Symfony 6 (базова версія)

Теги: 4.5.2. Перший реліз: 2023-02-17.

Це найраніший тегований реліз у репозиторії, тому він описаний як базова точка: що пакет уміє станом на 4.5.2. Історія проєкту починається з 2017 року (версії 1–3), але ці версії тут не теговані.

## Головне

- JSON-RPC 2.0 endpoint (`GET`/`POST` на одному маршруті) для Symfony 6 на PHP 8.1+.
- Будь-який сервіс, що реалізує `IRpcService`, стає RPC-процедурою; його публічні методи доступні як `ClassName.method`.
- Batch-запити з передачею результату одного виклику в параметри іншого (`@FROM:<id>(<field>)`).
- Паралельна обробка batch-запитів в окремих консольних процесах `ufo:rpc:process`.
- Доставка відповіді на callback URL через параметр `$rpc.callback`.
- Захист `GET`/`POST` токеном та експорт проєкту для SoapUI.

## Що нового

### RPC endpoint і карта сервісів

`ApiController` обслуговує один маршрут `ufo_rpc_api_server`. `POST` обробляє JSON-RPC запити; `GET` повертає карту сервісів у стилі SMD (на базі `laminas/laminas-json-server`) зі списком методів, параметрів і типів повернення. Вбудований метод `ping` повертає `"PONG"`.

**Як використати** — зареєструйте маршрут (Symfony Flex recipe з `ufo-tech/recipes` робить це автоматично):

```yaml
# config/routes/ufo_json_rpc.yaml
ufo_json_rpc:
    resource: ../../vendor/ufo-tech/json-rpc-bundle/config/router.yaml
    prefix: /api
    trailing_slash_on_root: false
```

```json
{"id": 1, "method": "ping"}
```

### Процедури через `IRpcService`

`Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService` має `#[AutoconfigureTag('ufo.rpc.service')]`; кожен сервіс із цим тегом реєструється в RPC-сервері. Методи називаються `<ShortClassName>.<method>`; параметри можна передавати за іменем або за позицією.

**Як використати:**

```php
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;

class MyRpcProcedure implements IRpcService
{
    public function sayHelloName(string $name): string
    {
        return 'Hello, ' . $name;
    }
}
```

```json
{"id": 1, "method": "MyRpcProcedure.sayHelloName", "params": {"name": "Mr. Anderson"}}
```

### Batch-запити з ланцюжком результатів

Тіло запиту, що починається з `[`, обробляється як batch. Значення параметра `@FROM:<requestId>(<field>)` замінюється полем з результату іншого запиту того ж batch; залежні запити виконуються після джерела.

**Як використати:**

```json
[
  {"id": "create", "method": "SomeEntityProcedure.create", "params": {"name": "test"}},
  {"id": "activate", "method": "SomeEntityProcedure.changeStatus",
   "params": {"id": "@FROM:create(id)", "status": 1}}
]
```

### Паралельна обробка batch і `ufo:rpc:process`

Незалежні запити batch запускаються окремими процесами консольної команди `ufo:rpc:process` (`RpcAsyncProcessor`), тож час відповіді близький до часу найповільнішого запиту. Таймаут процесу — 60 с за замовчуванням, змінюється параметром `$rpc.timeout`. Команду можна викликати й напряму.

**Як використати:**

```console
$ php bin/console ufo:rpc:process '{"id":1,"method":"ping"}' -t ClientTokenExample
```

Примітка: процесор запускає `../bin/console`, тобто очікує, що веб-процес працює з `public/`.

### Доставка на callback (`$rpc.callback`)

Якщо запит містить `$rpc.callback`, пакет надсилає POST із відповіддю методу на цей URL (`RpcCallbackProcessor`, Symfony HttpClient) і повертає об'єкт статусу `callback` замість результату.

**Як використати:**

```json
{"id": 1, "method": "SomeEntityProcedure.create",
 "params": {"name": "test", "$rpc.callback": "https://example.com/endpoint"}}
```

### Захист токеном

Вимкнений за замовчуванням. Якщо увімкнено, запити з HTTP-методом зі списку `protected_methods` мають передавати токен із `clients_tokens` у налаштованому заголовку. `ufo:rpc:process` вимагає `-t/--token`, якщо захищено `POST`. Власну перевірку можна реалізувати через `Security\Interfaces\ITokenValidator`.

**Як використати:**

```yaml
# config/packages/ufo_json_rpc.yaml
ufo_json_rpc:
    security:
        protected_methods: ['GET', 'POST']
        token_key_in_header: 'Ufo-RPC-Token'   # default
        clients_tokens:
            - '%env(resolve:UFO_API_TOKEN)%'
```

### Експорт для SoapUI

`GET <prefix>/soapui.xml` повертає проєкт SoapUI з усіма методами, який імпортується через *File → Import Remote Project*. Query-параметри: `token` (підставляється в параметри проєкту) і `show_examples` (`1` — приклади значень, за замовчуванням; `0` — типи).

## Зміни та виправлення

- Помилки повертаються як JSON-RPC error-об'єкти; винятки винесено в окремий пакет `ufo-tech/rpc-exceptions`, об'єкти запиту/відповіді — в `ufo-tech/rpc-objects`.
- Неперехоплені винятки на API-маршруті перетворюються на JSON-RPC відповідь з помилкою та HTTP 200 (`HandleExceptionListener`).
- Помилки логуються через PSR-3 logger, якщо він доступний.
- Symfony Flex recipe (`ufo-tech/recipes`) створює стандартні файли конфігурації та маршрутів.

## Примітки до оновлення

Початкова публічна база. Перехід з 3.x: пакет перероблено під PHP 8.1 і Symfony 6 (реліз 4.0, січень 2023); процедури тепер реєструються реалізацією `IRpcService` (autoconfigured тег `ufo.rpc.service`). Детальна інструкція міграції 3.x → 4.x у коді не задокументована.
