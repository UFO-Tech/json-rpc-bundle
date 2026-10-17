[English](README.md)

# 7.3 — Експорт у Postman, розширена JSON Schema, захист async

Теги: 7.3.10 (єдиний тегований реліз 7.3; 7.3.0–7.3.9 — це підняття версії в `composer.json` без git-тегів). Перший реліз: 2025-01-30.

## Головне

- Новий endpoint `GET /postman`, який експортує Postman Collection v2.1 з усіма RPC-методами.
- Генерація JSON Schema охоплює більше Symfony constraints: `All`, `Choice` (зокрема enum callbacks), `Collection`, `Date`, `GreaterThan`, `GreaterThanOrEqual`, `NotNull`, `Optional`, `Type` і `Uuid`. Вкладені constraints підтримуються.
- Виправлення безпеки: повідомлення, які обробляє async-обробник (Messenger), тепер проходять перевірку токена.
- Дочірній клас процедури може перевизначати RPC-методи батьківського без помилки «service already registered».
- Додано набір тестів PHPUnit і Docker-інструменти для розробки.

## Що нового

### Експорт колекції Postman

`ApiController::postmanAction` (маршрут `ufo_rpc_api_postman`, `GET /postman` відносно префікса маршрутів бандла) повертає колекцію, яку будує `PostmanAdapter`. Методи згруповано в папки з короткою назвою класу процедури. Кожен запит має заголовки `Content-Type`, `<security.token_name>: {{apiToken}}` і `AccessToken: {{accessToken}}`, а в колекції оголошено змінні `apiToken` та `accessToken`.

**Як використати**

```bash
curl https://example.test/api/postman > collection.json   # імпортуйте в Postman
```

### Більше constraints у згенерованій JSON Schema

Constraints, оголошені через `#[RPC\Assertions([...])]`, перекладаються в ключові слова JSON Schema у схемі параметра OpenRPC. Наприклад: `Uuid` → `format: uuid` разом з довжиною, `Date` → `format: date`, `Choice` → `enum`. Для `Choice` з callback `[EnumClass::class, 'method']` підставляються його значення. `All` → `items`, `Collection` → `properties`, а `Optional` зливає свої внутрішні правила. Непідтримувані constraints пропускаються з попередженням у PSR logger.

**Як використати**

```php
use Symfony\Component\Validator\Constraints as Assert;
use Ufo\RpcObject\RPC;

public function find(
    #[RPC\Assertions([new Assert\Uuid()])]
    string $id,
    #[RPC\Assertions([new Assert\All([new Assert\Choice(['new', 'paid'])])])]
    array $statuses = [],
): array { /* ... */ }
```

### Перевизначення процедур через наслідування

`ServiceMap::addService()` тепер приймає вже зареєстровану назву методу, якщо новий клас процедури є підкласом зареєстрованого: дочірній замінює батьківський. Якщо батьківський додається після дочірнього, він пропускається. Будь-який інший дублікат, як і раніше, викидає помилку.

## Зміни та виправлення

- **Безпека:** `RpcAsyncProcessor::__invoke()` тепер викликає `isValidRequest()` після встановлення токена з повідомлення. Якщо `protected_api` увімкнено, async RPC-повідомлення без токена або з невалідним токеном відхиляються.
- Async: callbacks (`params.$rpc.callback`) тепер надсилаються з Messenger-обробника. Callback-запити передають `Content-Type: application/json`. Вивід worker'а має позначки часу і не екранує Unicode.
- Виправлено дубль `null, null` для типу повернення `null`.
- Виправлено визначення типу для union-параметрів у нормалізаторі JSON Schema.
- `TaggedIterator` замінено на `AutowireIterator` (deprecation у Symfony).
- `ufo-tech/rpc-objects` зафіксовано на `2.7.*`.

## Примітки щодо оновлення

- Якщо ви використовуєте async-транспорти (Messenger) з `protected_api: true`, переконайтеся, що producers додають валідний клієнтський токен у кожне повідомлення. Повідомлення без токена, які раніше виконувалися, тепер відхиляються.
- `ufo-tech/rpc-objects` обмежено версією `2.7.*`.
