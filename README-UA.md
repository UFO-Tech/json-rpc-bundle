# JsonRpcBundle
![Ukraine](https://img.shields.io/badge/%D0%A1%D0%BB%D0%B0%D0%B2%D0%B0-%D0%A3%D0%BA%D1%80%D0%B0%D1%97%D0%BD%D1%96-yellow?labelColor=blue)

JSON-RPC 2.0 сервер для Symfony v.6.* і новіше

### Про цей пакет

Пакет для легкого створення API за допомогою JSON-RPC сервера  

>Робити RPC сервіси для сервіс-орієнтованої архітектури на Symfony ще ніколи не було так просто.

![License](https://img.shields.io/badge/license-MIT-green?labelColor=7b8185)
 ![Size](https://img.shields.io/github/repo-size/ufo-tech/json-rpc-bundle?label=Size%20of%20the%20repository)
 ![package_version](https://img.shields.io/github/v/tag/ufo-tech/json-rpc-bundle?color=blue&label=Latest%20Version&logo=Packagist&logoColor=white&labelColor=7b8185)
 ![fork](https://img.shields.io/github/forks/ufo-tech/json-rpc-bundle?color=green&logo=github&style=flat)

### Вимоги до оточення
![php_version](https://img.shields.io/packagist/dependency-v/ufo-tech/json-rpc-bundle/php?logo=PHP&logoColor=white)
 ![symfony_version](https://img.shields.io/packagist/dependency-v/ufo-tech/json-rpc-bundle/symfony/framework-bundle?label=Symfony&logo=Symfony&logoColor=white)
 ![symfony_version](https://img.shields.io/packagist/dependency-v/ufo-tech/json-rpc-bundle/symfony/serializer?label=SymfonySerializer&logo=Symfony&logoColor=white)
 ![symfony version](https://img.shields.io/packagist/dependency-v/ufo-tech/json-rpc-bundle/symfony/serializer?label=SymfonyCache&logo=Symfony&logoColor=white)

Цей пакет дозволяє легко створювати API за допомогою JSON-RPC сервера для Symfony v.6.* і більш нових версій. Він підходить для розробників, які хочуть швидко і ефективно інтегрувати RPC функціонал у свої проекти.
## Що нового в версія 10.0
1. **Підтримка версіонування API**  
   Реалізовано нативну підтримку версій API на рівні RPC‑методів. Дозволяє перевизначати або розширювати методи в нових версіях без дублювання попередньої логіки та зі зворотньою сумісністю для існуючих клієнтів.

1. **Нормалізація API-документації до актуальної версії OpenRPC**  
   - Генерація документації приведена у відповідність до актуальної специфікації OpenRPC. Покращено структуру схем, типізацію параметрів та сумісність із зовнішніми інструментами генерації клієнтів і документації.
   - Серед іншого, enum-типи автоматично визначаються та коректно відображаються у схемах документації, включаючи backed та кастомні enum-значення.

1. **Автоматичне перетворення параметрів запиту**  
   Додано автоматичну конвертацію вхідних параметрів RPC-запиту у DTO, Enum, Entity та інші типізовані об'єкти на основі сигнатури методу, що суттєво зменшує кількість ручного коду обробки запитів.

   - **Автопідключення сервісів у методи API** — підтримка інʼєкції сервісів безпосередньо в RPC-методи аналогічно контролерам Symfony через механізм autowire.
   - **Реалізація Doctrine ParamConverter** — автоматичне отримання сутностей Doctrine за ідентифікатором без необхідності ручних репозиторних викликів.

1. **Компіляція ServiceMap через Compiler Pass**  
   Формування карти RPC‑сервісів перенесено на етап компіляції контейнера Symfony, що дозволяє уникнути runtime‑рефлексії та значно покращує продуктивність виконання API.

1. **Внутрішній рефакторинг та виправлення помилок**  
   Проведено оптимізацію внутрішньої архітектури, спрощено життєвий цикл побудови ServiceMap, покращено стабільність роботи та усунено низку технічних обмежень попередніх версій.

### Основні переваги
- **Простота інтеграції:** Інтеграція пакету з вашим проектом є надзвичайно простою. Вам потрібно лише додати спеціальний інтерфейс до будь-якого існуючого класу, і він автоматично отримає можливість обробляти JSON-RPC запити.
- **Гнучкість:** Пакет забезпечує велику гнучкість при створенні API, дозволяючи розробникам легко розширювати і модифікувати поведінку сервера без втручання у вже існуючий код.
- **Ефективність:** Завдяки оптимізації викликів і використанню сучасних компонентів Symfony, пакет гарантує високу продуктивність при обробці запитів.

## [Документація](https://docs.ufo-tech.space/bin/view/docs/JsonRpcBundle/?language=uk)
Вичерпну документацію по встановленню, налаштуванню і використанню цієї бібліотеки ви знайдете на порталі документацій [UFO-Tech](https://docs.ufo-tech.space/?language=uk) (Universal Flexible Open Technologies)

[JsonRpcBundle](https://docs.ufo-tech.space/bin/view/docs/JsonRpcBundle/?language=uk)

## Кілька точок входу RPC API

Один застосунок може публікувати кілька RPC API з окремими картами методів.
Наприклад, public API надає клієнтам каталог товарів, а internal API
дозволяє змінювати ціни. Метод перегляду каталогу спільний для обох API.
Групу задає `defaults.api_group` в імпорті маршрутів; без цього параметра
використовується `general`. URL задається незалежно через `prefix`.

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

Bundle автоматично додає до назв маршрутів префікси `general_`, `internal_`, `public_`.
Ключ імпорту не є префіксом назви маршруту. Явний `name_prefix` не потрібен;
якщо він заданий, він додається після префікса групи. Старі назви маршрутів
загального API залишаються доступними як аліаси.

`GET /rpc/internal` повертає OpenRPC, `GET /rpc/internal/postman` — Postman,
`POST /rpc/internal` виконує RPC-запит. Версійні адреси мають вигляд
`/rpc/internal/v2` та `/rpc/internal/v2/postman`. Документація містить лише методи
й версії відповідної групи та URL цієї точки входу.

### Групи методів

У прикладі `catalog.list` доступний в internal і public API, а кожна група
також має власні методи. `false` виключає дефолтну групу `general`
зі списку груп класу.

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
    public function rebuildIndex(): true { return true; } // не доступний через RPC
}
```


| RPC-метод | General `/rpc` | Internal `/rpc/internal` | Public `/rpc/public` |
| --- | --- | --- | --- |
| `catalog.list` | Ні | Так | Так |
| `catalog.updatePrice` | Ні | Так | Ні |
| `catalog.featured` | Ні | Ні | Так |
| `catalog.status` | Так | Ні | Ні |
| `catalog.rebuildIndex` | Ні | Ні | Ні |

Атрибут методу повністю замінює групи класу. Без атрибута на методі
використовуються групи класу; без обох атрибутів — `new RPC\APIGroups()`.
Параметр `withDefault` за замовчуванням додає `general`; `false` вимикає
це додавання. `APIGroups([], false)` не публікує метод у жодній групі.
`IgnoreApi` має найвищий пріоритет. `general` не є об'єднанням усіх API.

Карти та успадкування версій розділені за групами. Метод іншої групи
повертає `Method not found`, без переходу до загального API. Групу HTTP-запиту
визначає маршрут, а не поле в JSON.

### Провайдери токенів

Чинні налаштування security залишаються сумісними. Без `providers`
базовий `ITokenValidator` перевіряє всі точки входу. Стандартна реалізація
використовує `clients_tokens`; перевизначення базового валідатора зберігається.

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

Сервіс провайдера реєструється у Symfony DI та реалізує
`Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator` з методом
`isValid(string $token): true`. Невалідний токен має спричинити
`RpcInvalidTokenException`.

Призначений провайдер замінює базову перевірку своєї групи. Після відхилення
токена базова перевірка не запускається. Групи без провайдера використовують
базовий валідатор. Два провайдери для однієї групи, відсутній сервіс або
неправильний інтерфейс — помилка компіляції контейнера.
`protected_api`, `protected_doc` і `token_name` спільні для всіх груп.
Захищена документація використовує провайдера відповідної групи.

Для простої перевірки токенів задайте окремі env-ключі для кожної групи
замість реєстрації власних сервісів:

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

Кожен провайдер має задавати рівно один параметр: `service` або `clients_tokens`.
Для `clients_tokens` bundle створює окремий `SimpleTokenValidator`;
реєструвати сервіс у застосунку не потрібно. Список може містити кілька ключів
для ротації токенів. Порожній список відхиляє всі токени без базової перевірки.

У цьому прикладі internal API приймає лише власні ключі, public API — власні.
Базовий `UFO_API_TOKEN` використовується для `general` та інших груп без
призначеного провайдера. Захищена документація використовує той самий вибір
провайдера.

### Кастомний валідатор токенів без груп API

Залиште один імпорт маршруту `/rpc` без `defaults.api_group`, а RPC-сервіси —
без атрибутів `APIGroups`. Кастомний валідатор підключається як базовий
`ITokenValidator` через аліас Symfony-сервісу; блок `providers` не потрібен:

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

Кастомний валідатор перевіряє всі захищені точки входу без призначеного
групового провайдера, включно з документацією, якщо `protected_doc: true`.
`clients_tokens` не потрібен, якщо ваш валідатор не використовує цей параметр.

Альтернативно, єдиний API неявно належить до `general`, тому йому можна
явно призначити провайдера:

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

У цьому варіанті зареєструйте валідатор як Symfony-сервіс; аліас інтерфейсу
не потрібен. Відсутній `api_groups` або значення `[]` не призначає провайдера
жодній групі й не робить його базовим валідатором.

### CLI, batch та async

CLI підтримує `--api-group=internal`; без опції використовується `general`.
Усі елементи HTTP batch належать групі й версії маршруту; дочірні процеси
отримують ці значення. Для Messenger потрібно передати групу явно:

```php
$message = new \Ufo\RpcObject\RpcAsyncRequest(
    $request,
    $token,
    apiGroup: 'internal',
);
```

Повідомлення без групи використовують `general`. Обробник встановлює групу
до перевірки токена й виконання та скидає її після повідомлення, включно з
помилками. Для HTTP група встановлюється безпосередньо в `ApiGroupContext`
після визначення маршруту, до перевірки токена та виконання. Контекст зберігається
до завершення відкладеної обробки й скидається після неї.

### Спільний кеш і відповідальність розробника

Кеш залишається спільним для всіх груп, з чинними ключами. Група не додається
до ключів кешу метаданих сервісів або RPC-відповідей.

**Однойменні RPC-методи в різних групах API, реалізовані різними класами,
є нештатним використанням сервера.** Через спільний кеш одна група може
отримати кешований результат або метадані іншої реалізації. Відповідальність
за такі конфлікти та коректність результатів лежить на розробнику застосунку,
який використовує сервер. Для незалежних реалізацій використовуйте різні
RPC-назви. Конфлікт однакових назв у межах однієї групи й версії відхиляється
з урахуванням чинних правил успадкування класів.
