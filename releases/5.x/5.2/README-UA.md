[English](README.md)

# 5.2 — Власна карта сервісів, валідація параметрів і JSON Schema

Теги: 5.2.0. Перший реліз: 2024-05-02.

## Головне

- Прибрано залежність від Laminas: сервер, карта сервісів і рефлексія методів тепер реалізовані в пакеті (`ServiceLocator`, `Service`, `UfoReflectionProcedure`).
- Валідація параметрів обмеженнями Symfony Validator, оголошеними через `#[RPC\Assertions]`.
- JSON Schema (draft-07) для параметрів методів, згенерована з цих обмежень і опублікована в документації API.
- Повніша документація з PHP-типів і docblock: описи, nullable/union типи, значення за замовчуванням, ознака optional.
- Іменування процедур через `#[RPC\Info]` (аліас і роздільник); новий endpoint `GET <prefix>/method/{method}` для одного методу.
- Експорт для SoapUI видалено.

## Що нового

### Валідація параметрів (`#[RPC\Assertions]`)

Параметри методу можуть мати атрибут `Ufo\RpcObject\RPC\Assertions` зі списком обмежень Symfony. Перед викликом `Validations\RpcValidator` перевіряє кожен параметр; у разі порушень клієнт отримує JSON-RPC помилку (`ConstraintsImposedException`, різновид `RpcBadParamException`), у `data` якої — повідомлення для кожного параметра.

**Як використати:**

```php
use Symfony\Component\Validator\Constraints as Assert;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

class UserProcedure implements IRpcService
{
    public function create(
        #[RPC\Assertions([new Assert\NotBlank(), new Assert\Email()])]
        string $email,
    ): int {
        // ...
    }
}
```

### JSON Schema в документації

Для кожного методу документація `GET` містить `json_schema` (draft-07, `type: object`, `properties`, `required`), побудовану з типів параметрів і обмежень. У ключові слова схеми перетворюються обмеження: `NotBlank`, `Length`, `Range`, `Email`, `EqualTo`, `Regex`.

**Як використати:** додайте `#[RPC\Assertions]`, як вище, і виконайте `GET /api` або `GET /api/method/UserProcedure.create`.

### Документація з типів і docblock

Карта сервісів будується рефлексією публічних методів: summary docblock → опис методу, описи з `@param`, нативні типи або типи з `@return` (включно з nullable і union), значення за замовчуванням і `optional`. `#[RPC\Response]` (з `ufo-tech/rpc-objects`) на методі заповнює `responseFormat` у документації; його аргументи визначені в `rpc-objects` і в цьому пакеті не задокументовані.

### Іменування процедур (`#[RPC\Info]`)

`#[RPC\Info]` на класі процедури задає аліас, який використовується замість короткого імені класу, і роздільник між аліасом і методом (за замовчуванням `.`). Вбудований `PingProcedure` має `#[RPC\Info("")]`, тому його метод називається просто `ping`.

**Як використати:**

```php
#[RPC\Info('user')]
class UserProcedure implements IRpcService { /* user.create, ... */ }
```

### Документація одного методу

`GET <prefix>/method/{method}` (маршрут `ufo_rpc_api_collection`) повертає документацію одного методу; для невідомого імені — помилка "method not found". Захист токеном діє так само, як для основного маршруту.

## Зміни та виправлення

- `RpcServer` більше не наслідує `Laminas\Json\Server\Server`; envelope карти сервісів — `JSON-RPC-2.0/UFO-RPC-5`.
- PHP `TypeError` під час виклику повертається як `RpcBadParamException` зі зрозумілим повідомленням (`Parameter "x" ...`).
- Відсутній обов'язковий іменований параметр дає помилку `Required parameter "x" not passed`.
- Перевірка токена тепер визначає захищені маршрути через Symfony `UrlMatcher` (основний маршрут і `/method/{method}`), а не порівнянням сирого URI.
- CLI: значення `ufo:rpc:process --token` стало необов'язковим; CLI-захист перевіряє токен лише тоді, коли `POST` є в `protected_methods`.
- Відповідь на неперехоплену помилку: ключ `jsorpc` виправлено на `jsonrpc`; обробка поширена на маршрут `/method/{method}`.
- Контекст логу помилок: масив `data` серіалізується в YAML; логування працює й без об'єкта запиту.
- Відома проблема в 5.2.0: якщо валідація не пройдена, клієнт отримує помилку, але `RpcServer::dispatch()` усе одно викликає метод (виправлено в 6.x).
- Репозиторій: додано `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`, шаблони issue.

## Примітки до оновлення

- Потрібен Symfony `^6.4` (було `6.*`); нові залежності `phpdocumentor/reflection-docblock` і `symfony/expression-language`. Пакети `laminas/*` більше не встановлюються — додайте їх самі, якщо ваш код їх використовував.
- Експорт SoapUI (`<prefix>/soapui.xml`) і класи `SoupUi\*` видалено.
- Формат документації `GET` змінився (власний `ServiceLocator::toArray()`; ключі методу `name`, `decription` (саме так, з помилкою), `parameters`, `json_schema`, `returns`, `responseFormat`). Оновіть клієнтів, які його розбирають.
- Зміни API для коду, що розширює пакет: `IFacadeRpcServer::addProcedure()` втратив аргумент `$argv`, `getServiceMap()` повертає `ServiceLocator|RpcResponse`, додано `handleSmRequest()`; змінено конструктори `RpcServer` і `RpcServerFacade`; видалено `DependencyInjection\Compiler\ApiProceduresPass`.
- Ключі конфігурації не змінилися.
