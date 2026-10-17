[English](README.md)

# 10.1 — Symfony 8, JSON-RPC нотифікації, суворіший протокол

Теги: 10.1.0. Перший реліз: 2026-09-25.

## Головне

- **Підтримка Symfony 8** (`^7.2|^8.0`); тепер потрібен PHP >= 8.4.
- **JSON-RPC нотифікації**: запит без `id` не отримує тіла відповіді (HTTP `204`) і виконується після відправлення відповіді.
- **Суворіший JSON-RPC 2.0**: непідтримувана версія `jsonrpc` і порожній batch відхиляються з `-32600`.
- **Єдиний формат помилок параметрів**: відсутні, неправильного типу й невалідні параметри повертають одну помилку з повідомленнями по кожному параметру.

## Що нового

### Symfony 8

Усі залежності `symfony/*` — `^7.2|^8.0`. `symfony/messenger` став явною залежністю.

### JSON-RPC нотифікації

Запит без `id` — нотифікація (`Ufo\RpcObject\RpcNotificationRequest`, з `ufo-tech/rpc-objects` ^3.8). Сервер не повертає на неї відповідь: одиночна нотифікація по HTTP отримує `204 No Content` з порожнім тілом. Метод виконується на `RpcEvent::POST_RESPONSE`, тобто після відправлення відповіді (після `fastcgi_finish_request()` під PHP-FPM).

**Як використовувати**

```bash
curl -i -X POST https://example.com/rpc \
  -H 'Content-Type: application/json' -H 'Ufo-RPC-Token: <token>' \
  -d '{"jsonrpc": "2.0", "method": "audit.log", "params": {"event": "login"}}'
# HTTP/1.1 204 No Content
```

Сигнатура методу не змінюється; його результат відкидається.

### Перевірки протоколу

- `jsonrpc`, відмінний від `"2.0"` → `RpcBadRequestException` (`-32600`, "Unsupported jsonrpc protocol version").
- Порожній batch `[]` → одна відповідь з помилкою (`-32600`, "Can`t process empty batch request").

### Єдиний формат помилок параметрів

Відсутні обов'язкові параметри, PHP `TypeError` на параметрі та невдала конвертація в DTO тепер дають `ConstraintsImposedException` з повідомленням `Invalid Data for call method: <method>` і проблемами по кожному параметру в `error.data`. Простори імен класів прибираються з повідомлень. Раніше відсутній параметр давав звичайний `RpcBadParamException` лише для першого з них.

```json
{
  "jsonrpc": "2.0", "id": 1,
  "error": {
    "code": -32602,
    "message": "Invalid Data for call method: user.create",
    "data": {"email": "Required parameter \"email\" not passed"}
  }
}
```

## Зміни та виправлення

- Помилки всередині колекцій DTO прив'язуються до правильного елемента (`errors[param][key]`).
- Видалено невикористовуваний `DTOTransformerInitListener`.
- Тести переведено на PHPUnit ^11.5, додано функціональне тестове ядро й тести відповідності протоколу.

## Примітки до оновлення

- PHP >= 8.4 (було >= 8.3).
- `ufo-tech/rpc-exceptions` ^2 (було ^1.3), `ufo-tech/rpc-objects` ^3.8, `ufo-tech/dto-transformer` ^3.1.
- Клієнти, що надсилали запити без `id` і чекали результат, мають передавати `id`.
- Обробку помилок, що спиралася на повідомлення `RpcBadParamException` про відсутні/неправильні параметри, переведіть на `error.data`.
- Виконання нотифікації виводить запит і відповідь у stdout через `echo` (у 10.1 і 10.2); у [10.3](../10.3/README-UA.md) замінено на логування.
