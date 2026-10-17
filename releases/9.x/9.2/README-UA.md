[English](README.md)

# 9.2 — Неблокувальні callback-запити, полегшена інформація документації

Теги: 9.2.0, 9.2.1. Перший реліз: 2026-01-13.

## Головне

- Запити з `$rpc.callback` більше не блокують клієнта: пакет відповідає одразу, а метод виконує після відправлення HTTP-відповіді (9.2.0).
- Доставка на callback пересилає заголовки вхідного HTTP-запиту на callback URL (9.2.0).
- Слухачі `rpc.post_response` тепер виконуються й для консольних запитів (`ufo:rpc:process`), бо `fastcgi_finish_request()` викликається лише для HTTP-carrier (9.2.0).
- `GET <api>?info` повертає лише заголовок OpenRPC (info, servers) без методів і компонентів (9.2.1).

## Що нового

### Неблокувальні callback-запити (9.2.0)

До 9.2 запит з `$rpc.callback` виконувався синхронно, результат надсилався POST-запитом на callback, а клієнт отримував статус цієї доставки. Тепер `RpcRequestHandler` одразу повертає коротке підтвердження і запускає `rpc.pre_response`. Сама робота відбувається в `AsyncServerEventListener::processAsync()` на події `rpc.post_response`: він викликає `RpcAsyncProcessor::processAsync()`, який виконує метод і надсилає результат на callback через `RpcCallbackProcessor`. Під PHP-FPM це відбувається після `fastcgi_finish_request()`, тобто коли клієнт уже отримав відповідь.

**Як використати:**

```json
{
  "id": "order-42",
  "method": "Orders.export",
  "params": {"id": 42, "$rpc": {"callback": "https://client.example/rpc-callback"}}
}
```

Миттєва відповідь:

```json
{"id": "order-42", "result": {"async": true, "callback": "https://client.example/rpc-callback"}, "jsonrpc": "2.0"}
```

Повна JSON-RPC відповідь потім надсилається POST-запитом на `https://client.example/rpc-callback`.

### Пересилання заголовків на callback (9.2.0)

`RpcCallbackProcessor` копіює всі заголовки вхідного HTTP-запиту (якщо carrier надає `getHttpRequest()`) у запит на callback; `Content-Type` і `Accept` завжди встановлюються в `application/json`. Це дає callback-endpoint змогу автентифікувати виклик тими самими заголовками, які надіслав клієнт. Зважайте, що серед них є і заголовок RPC-токена (`Ufo-RPC-Token` за замовчуванням) та будь-які інші облікові дані.

### Полегшена інформація документації (9.2.1)

`OpenRpcAdapter::adapt(bool $fullInfo = true)` може не будувати методи й компоненти. `ApiController::serverAction()` передає `false`, якщо в запиті є query-параметр `info`.

**Як використати:**

```bash
curl 'https://example.com/api?info'
```

## Зміни та виправлення

- 9.2.0 — `RpcServer::$serviceHolder` тепер `readonly public`. `RpcCallbackProcessor` залежить від `RequestCarrier`; `AsyncServerEventListener` — від `RpcAsyncProcessor`.
- 9.2.1 — Підтримка `GET ?info`. `OpenRpcAdapter` більше не передає прапорець deprecated у `OpenRpcSpecBuilder::buildMethod()`, тому в 9.2.1 методи не позначаються `deprecated` в OpenRPC, навіть якщо мають `@deprecated` / `#[\Deprecated]` (прапорець і далі збирається в `Service::isDeprecated()`). У `composer.json` додано другого автора. **Виправлено в [10.3.1](../../10.x/10.3/README-UA.md#1031).**

## Примітки до оновлення

- Клієнти, які надсилають `$rpc.callback` і розбирають миттєву відповідь, мають обробляти нову форму `{"async": true, "callback": "<url>"}`. Старого результату `{"callback": {"url", "status", "data"}}` більше немає, а помилки доставки на callback у миттєвій відповіді не повідомляються.
- Callback-endpoint тепер отримують заголовки запиту клієнта, включно з RPC-токеном. Переконайтеся, що callback URL ведуть на довірені хости.
- Для HTTP-запитів етап post-response (а отже і робота з callback) і далі починається з `fastcgi_finish_request()`. У SAPI без цієї функції (напр. mod_php, вбудований сервер PHP) виклик кидає помилку, вона поглинається, і метод не виконується. Якщо ви покладаєтеся на `$rpc.callback`, запускайте API під PHP-FPM.
