[English](README.md)

# 10.2 — Групи API: кілька точок входу RPC в одному застосунку

Теги: 10.2.1, 10.2.3. Перший реліз: 2026-10-15.

## Головне

- **Групи API**: кілька RPC API (наприклад, public, internal, окремі BFF) з одного застосунку, кожен зі своїм URL, картою методів, версіями й документацією.
- **`#[RPC\APIGroups]`** на класі або методі визначає, до яких груп належить метод.
- **Перевірка токенів для кожної групи** через `security.providers`: власний сервіс-валідатор або окремий список токенів.
- **Завантажувачі маршрутів** (`Routing\ApiYamlFileLoader`, `RpcYamlFileLoader`), які можуть розширювати bundle-адаптери.
- Вибір групи для **CLI** (`--api-group`), дочірніх процесів **batch** і **Messenger** (`RpcAsyncRequest(..., apiGroup:)`).

Повна зворотна сумісність для застосунків без груп: усе належить до групи за замовчуванням `general`.

## Що нового

### Кілька точок входу RPC API

Приклад: public API надає клієнтам каталог товарів, а internal API дозволяє змінювати ціни. Метод перегляду каталогу спільний для обох API.

**Як використовувати**

Імпортуйте маршрути bundle один раз для кожного API (`config/routes/ufo_json_rpc.yaml`). `prefix` задає URL незалежно від групи. Групу задає `defaults.api_group`; без цього параметра використовується `general`. Наявні імпорти маршрутів продовжують працювати.

```yaml
ufo_json_rpc:
    resource: ../../vendor/ufo-tech/json-rpc-bundle/config/router.yaml
    prefix: /rpc
    trailing_slash_on_root: false

ufo_json_rpc_internal:
    resource: ../../vendor/ufo-tech/json-rpc-bundle/config/router.yaml
    prefix: /rpc/internal
    defaults:
        api_group: internal
    trailing_slash_on_root: false

ufo_json_rpc_public:
    resource: ../../vendor/ufo-tech/json-rpc-bundle/config/router.yaml
    prefix: /rpc/public
    defaults:
        api_group: public
    trailing_slash_on_root: false
```

Bundle автоматично додає до назв маршрутів префікси `general_`, `internal_`, `public_`; ключ імпорту не є префіксом назви маршруту. Явний `name_prefix` не обов'язковий; якщо він заданий, додається після префікса групи. Старі назви маршрутів загального API залишаються аліасами.

`POST /rpc/internal` виконує RPC-запит, `GET /rpc/internal` повертає OpenRPC, `GET /rpc/internal/postman` — Postman. Версійні адреси: `/rpc/internal/v2` та `/rpc/internal/v2/postman`. Документація містить лише методи й версії відповідної групи та URL цієї точки входу; назва колекції Postman отримує суфікс `[група]` (10.2.3).

### Групи методів: `#[RPC\APIGroups]`

`RPC\APIGroups(array $groups = [], bool $withDefault = true)` (з `ufo-tech/rpc-objects` ^3.9). У прикладі `catalog.list` доступний в internal і public API, а кожна група має й власні методи. `false` виключає групу за замовчуванням `general`.

```php
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

#[RPC\Info('catalog')]
#[RPC\APIGroups(['internal', 'public'], false)]
class CatalogApi implements IRpcService
{
    public function list(): array
    {
        return [['id' => 1, 'name' => 'Coffee']];
    } // спільний для internal і public API

    #[RPC\APIGroups(['internal'], false)]
    public function updatePrice(int $productId, int $price): true { return true; } // лише internal

    #[RPC\APIGroups(['public'], false)]
    public function featured(): array { return [1]; } // лише public

    #[RPC\APIGroups()]
    public function status(): string { return 'ok'; } // лише general

    #[RPC\APIGroups(['internal'], false)]
    #[RPC\IgnoreApi]
    public function rebuildIndex(): true { return true; } // ніколи не публікується
}
```

| RPC-метод | General `/rpc` | Internal `/rpc/internal` | Public `/rpc/public` |
| --- | --- | --- | --- |
| `catalog.list` | Ні | Так | Так |
| `catalog.updatePrice` | Ні | Так | Ні |
| `catalog.featured` | Ні | Ні | Так |
| `catalog.status` | Так | Ні | Ні |
| `catalog.rebuildIndex` | Ні | Ні | Ні |

Правила:

- Атрибут методу повністю замінює групи класу. Без нього діють групи класу; без обох атрибутів — `new RPC\APIGroups()` (= `general`).
- `withDefault: true` додає `general`; `false` — ні. `APIGroups([], false)` не публікує метод у жодній групі.
- `IgnoreApi` має пріоритет над групами.
- `general` не є об'єднанням усіх груп.
- Карти сервісів і успадкування версій розділені за групами. Метод іншої групи повертає `Method not found`, без переходу до іншого API.
- Для HTTP групу визначає маршрут, а не поле в JSON.

### Провайдери перевірки токенів

Чинні налаштування security і перевизначення базового `ITokenValidator` залишаються сумісними. Без `providers` базовий валідатор перевіряє всі групи; стандартний `SimpleTokenValidator` використовує `clients_tokens`.

**Як використовувати: сервіс-валідатор для групи**

```yaml
ufo_json_rpc:
    security:
        protected_api: true
        protected_doc: false
        token_name: 'Ufo-RPC-Token'
        clients_tokens:
            - '%env(resolve:UFO_API_TOKEN)%'
        providers:
            internal:
                service: App\Services\RpcSecurity\InternalTokenValidator
                api_groups: [internal]
            public:
                service: App\Services\RpcSecurity\UserTokenValidator
                api_groups: [public]
```

Провайдер — Symfony-сервіс, що реалізує `Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator` (`isValid(string $token): true`); невалідний токен відхиляється винятком `RpcInvalidTokenException`.

- Призначений провайдер замінює базову перевірку для своїх груп; після відхилення токена базова перевірка не запускається.
- Групи без провайдера використовують базовий валідатор.
- Два провайдери для однієї групи, відсутній сервіс або клас без `ITokenValidator` — помилка компіляції контейнера.
- `protected_api`, `protected_doc` і `token_name` спільні для всіх груп. Захищена документація використовує провайдера своєї групи.

**Як використовувати: список токенів для групи**

```yaml
ufo_json_rpc:
    security:
        protected_api: true
        protected_doc: false
        token_name: 'Ufo-RPC-Token'
        clients_tokens:
            - '%env(resolve:UFO_API_TOKEN)%'
        providers:
            internal:
                api_groups: [internal]
                clients_tokens:
                    - '%env(resolve:UFO_INTERNAL_API_TOKEN)%'
            public:
                api_groups: [public]
                clients_tokens:
                    - '%env(resolve:UFO_PUBLIC_API_TOKEN)%'
```

Кожен провайдер задає рівно один параметр: `service` або `clients_tokens`; `api_groups` обов'язковий і непорожній. Для `clients_tokens` bundle реєструє окремий `SimpleTokenValidator`; сервіс у застосунку не потрібен. Список може містити кілька ключів (ротація токенів). У прикладі internal API приймає лише internal-ключі, public API — лише public; `UFO_API_TOKEN` діє для `general` та інших груп без провайдера.

### Кастомний валідатор токенів без груп API

Залиште один імпорт `/rpc` без `defaults.api_group`, а RPC-сервіси — без `APIGroups`. Підключіть валідатор як базовий `ITokenValidator` через аліас; блок `providers` не потрібен:

```yaml
# config/packages/ufo_json_rpc.yaml
ufo_json_rpc:
    security:
        protected_api: true
        protected_doc: false
        token_name: 'Ufo-RPC-Token'
```

```yaml
# config/services.yaml
services:
    App\Services\RpcSecurity\UserTokenValidator:
        autowire: true

    Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator:
        alias: App\Services\RpcSecurity\UserTokenValidator
```

Валідатор перевіряє всі захищені точки входу без групового провайдера, включно з документацією при `protected_doc: true`. `clients_tokens` з 10.2 необов'язковий (за замовчуванням `[]`).

Альтернативно, єдиний API неявно належить до `general`, тож йому можна явно призначити провайдера (зареєструйте валідатор як сервіс; аліас не потрібен):

```yaml
ufo_json_rpc:
    security:
        protected_api: true
        protected_doc: false
        token_name: 'Ufo-RPC-Token'
        providers:
            user:
                service: App\Services\RpcSecurity\UserTokenValidator
                api_groups: [general]
```

### Завантажувачі маршрутів для API-адаптерів

`Routing\ApiYamlFileLoader` (абстрактний) відповідає за валідацію API-групи, встановлення `ApiGroupContext` до генерації маршрутів і відновлення після успіху чи помилки, префікси назв, пріоритети та старі аліаси `general`. `Routing\RpcYamlFileLoader` (тег `routing.loader`, пріоритет 100) застосовує це до RPC-імпортів bundle. Bundle-адаптери можуть успадкувати `RpcYamlFileLoader`, зберігши обробку RPC-імпортів і додавши власні:

```php
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Ufo\JsonRpcBundle\Routing\RpcYamlFileLoader;

#[AutoconfigureTag('routing.loader', ['priority' => 200])]
class AdapterYamlFileLoader extends RpcYamlFileLoader
{
    protected function getRouteNamePrefixAttribute(array $config, string $path): ?string
    {
        if ($this->isResourceImport($config['resource'], $path, __DIR__.'/../../config/router.yaml')) {
            return '_adapter_name_prefix';
        }

        return parent::getRouteNamePrefixAttribute($config, $path);
    }
}
```

Повернений ключ зберігає префікс групи/назв у defaults маршруту; `null` залишає імпорт без змін. Перевизначте `isApiRoute(Route $route, string $prefixAttribute): bool`, якщо адаптер обробляє лише частину імпортованих маршрутів. Якщо обробка RPC-імпортів не потрібна, успадковуйте `ApiYamlFileLoader` безпосередньо.

За ввімкнених autowiring і autoconfiguration успадкований конструктор отримує file locator, середовище та спільний `ApiGroupContext`. Реєстрацію маршрутів залишайте у власному loader адаптера або перевизначайте Symfony hook `parseRoute`: під час імпорту routing-ресурсу API група вже встановлена.

### CLI, batch та async

```bash
bin/console ufo:rpc:process '{"jsonrpc":"2.0","id":1,"method":"catalog.updatePrice","params":{"productId":1,"price":100}}' \
    --api-group=internal --ver=v1
```

`--api-group` за замовчуванням `general`. Дочірні процеси HTTP batch зберігають групу й версію маршруту. Для Messenger групу треба передати явно:

```php
$message = new \Ufo\RpcObject\RpcAsyncRequest(
    $request,
    $token,
    apiGroup: 'internal',
);
```

Повідомлення без групи використовують `general`. Обробник встановлює групу до перевірки токена й виконання та скидає її після, включно з помилками. Для HTTP група встановлюється в `ApiGroupContext` одразу після визначення маршруту; вона доступна для відкладеного виконання й скидається після нього.

### Спільний кеш і відповідальність розробника

> Змінено після 10.2.3: див. [../10.3/README-UA.md](../10.3/README-UA.md)

Кеш і ключі кешу спільні для всіх груп. Група не входить до ключів кешу метаданих сервісів або відповідей.

**Однойменні RPC-методи в різних групах API, реалізовані різними класами, є нештатним використанням сервера.** Через спільний кеш одна група може отримати кешований результат або метадані іншої реалізації; відповідальність за такі конфлікти лежить на розробнику застосунку. Для незалежних реалізацій використовуйте різні RPC-назви. Однакові назви в межах однієї групи й версії відхиляються з урахуванням чинних правил успадкування класів.

## Зміни та виправлення

- Batch-запити на версійний маршрут (`/rpc/v2`) тепер виконують кожен елемент у цій версії (раніше — у версії за замовчуванням).
- Пошук сервісу для async/нотифікацій враховує версію API запиту.
- Версійні маршрути документації (`/rpc/v2`, `/rpc/v2/postman`) тепер підпадають під `protected_doc`.
- Async-процеси запускаються як `php <project_dir>/bin/console ...` і отримують `--api-group` та `--ver`.
- 10.2.3: назва колекції Postman містить групу; виділено `AbstractPostmanAdapter` як базу для bundle-адаптерів.

## Примітки до оновлення

- `ufo-tech/rpc-objects` ^3.9 (для `RPC\APIGroups` і `RpcAsyncRequest::$apiGroup`).
- Змінювати конфігурацію не потрібно; без груп поведінка не змінюється.
- Конструктор `SimpleTokenValidator` змінено з `(RpcMainConfig $rpcConfig)` на `(array $tokens = [])`; оновіть підкласи або ручне створення. Bundle передає `$tokens` з `security.clients_tokens`.
- Назви маршрутів bundle тепер мають префікс групи (`general_ufo_rpc_api_server`, `internal_ufo_rpc_api_server`, ...). Старі назви (`ufo_rpc_api_server`, ...) лишаються аліасами лише для `general`, тож `router->generate()` для API за замовчуванням працює як раніше.
- `ServiceMap`, `ServiceHolder`, `TokenRpcSecurity`, `RpcAsyncProcessor`, `DocSecurityListener` отримали нові необов'язкові аргументи конструктора (`ApiGroupContext` тощо); власні підкласи зі своїми конструкторами мають їх передавати.
