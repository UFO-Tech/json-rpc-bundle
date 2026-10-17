[English](README.md)

# 8.1 — Схеми OpenRPC з PHPDoc, колекції DTO, схеми enum

Теги: 8.1.0, 8.1.1, 8.1.2, 8.1.3, 8.1.4, 8.1.5, 8.1.6, 8.1.7, 8.1.8, 8.1.9, 8.1.10, 8.1.11, 8.1.12, 8.1.13, 8.1.14. Перший реліз: 2025-07-02.

## Головне

- **Схеми OpenRPC з PHPDoc**: типи з `@param` / `@return` на кшталт `ItemDTO[]`, `array<int, ItemDTO>` чи union дають справжні JSON Schema (items, `oneOf`, посилання на DTO); короткі імена класів розв'язуються через `use`-імпорти процедури.
- **Колекції DTO як параметри**: параметр `array`, описаний як `@param ItemDTO[] $items`, гідрується в масив об'єктів `ItemDTO`.
- **Схеми enum скрізь**: параметри `BackedEnum`, властивості DTO, масиви enum та union-типи отримують значення `enum` у документації.
- **Типи властивостей DTO з PHPDoc** (`@var`, `@param`, promoted-параметри конструктора) використовуються в схемі.

## Що нового

### Побудова схем з PHPDoc (8.1.1, доопрацьовано в 8.1.2–8.1.3)

Рядок типу з тегів `@param` і `@return` методу перетворюється на JSON Schema (`TypeHintResolver::typeDescriptionToJsonSchema()` з `ufo-tech/dto-transformer`). Імена класів у ньому замінюються на DTO-схеми в `components`. Якщо `@return` вказує клас (або масив класу), він стає форматом результату (`ResultAsDTO`). Для властивостей DTO без атрибута `#[RPC\DTO]` використовується тип з тегу `@var`/`@param` властивості або з `@param` конструктора для promoted-властивості.

**Як використовувати:**

```php
use App\DTO\ItemDTO;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

#[RPC\Info('order')]
class OrderProcedure implements IRpcService
{
    /**
     * @param ItemDTO[] $items
     * @param array<string, int|string> $meta
     * @return ItemDTO[]
     */
    public function save(array $items, array $meta = []): array
    {
        // ...
    }
}
```

`GET` на RPC-ендпоінт тепер показує `items` як `array` зі схемою `ItemDTO`, а результат — як масив `ItemDTO`.

### Колекції DTO як параметри (8.1.10)

Якщо PHPDoc параметра `array` вказує клас як тип елемента, параметр реєструється як колекція DTO, і кожен елемент перед викликом будується через `DTOTransformer::fromArray()`. З 8.1.11 конвертація пропускається, якщо вхідне значення не масив.

**Як використовувати:** див. приклад `save(array $items)` вище — `$items` надходить як `ItemDTO[]`.

### Схеми enum (8.1.6–8.1.14)

Типи `BackedEnum` (включно з nullable/union) отримують `type` і `enum` у схемі параметра; з 8.1.13 схему будує `EnumsHelper::generateEnumSchema()` (`ufo-tech/dto-transformer`), з 8.1.14 вона застосовується до елементів масиву та до відповідної гілки `oneOf`. Обмеження `Choice` кладуть свій `enum` у відповідну гілку `oneOf` (8.1.12) і задають `type` як `string` або `integer` (8.1.13).

**Як використовувати:**

```php
enum Status: string { case NEW = 'new'; case PAID = 'paid'; }

/**
 * @param Status[] $statuses
 */
public function find(Status $status, array $statuses = []): array
```

### Зв'язки в документації (лише 8.1.0)

8.1.0 додав `x-ufo.relations` до блоку server в OpenRPC, що заповнюється з параметра контейнера `json_rpc_sdk` (`vendors[].name`). У 8.1.1 цей вивід знову прибрали (параметр і далі читається в `RpcMainConfig::$sdkVendors`, але не публікується), і до 8.1.14 його немає.

## Зміни та виправлення

- 8.1.1: Підняті залежності: `ufo-tech/dto-transformer` ^1.1, `ufo-tech/rpc-objects` ^3.1, `ufo-tech/rpc-exceptions` ^1.3. Валідація використовує `Ufo\RpcError\ConstraintsImposedException`. **Регресія**: цей реліз відкотив кілька змін 8.0.4–8.1.0 — схеми enum, ключі `XUfoValuesEnum` (замість них літерали `x-ufo`, `x-method`, `x-ufo-assertions`), спеціальні параметри в `RequestCarrier`, `x-ufo.relations`, а також видалив `Ufo\JsonRpcBundle\Exceptions\ConstraintsImposedException`.
- 8.1.2: Відновлено `Ufo\JsonRpcBundle\Exceptions\ConstraintsImposedException`; визначення DTO для object-параметрів, описаних у PHPDoc.
- 8.1.3: Схема результату бере нативний тип повернення, якщо немає `@return`; імена класів у вкладених властивостях DTO, масивах і `oneOf` розв'язуються в DTO-схеми; параметри з union-типом дають `oneOf`.
- 8.1.4: Розбір PHPDoc властивостей DTO більше не падає; якщо немає `@param`, використовується `@var`.
- 8.1.5: `RequestCarrier::getSpecialParams()` знову повертає спеціальні параметри (виправлення регресії 8.1.1).
- 8.1.6–8.1.7: Відновлено схеми enum для параметрів і властивостей DTO; працює для nullable/union enum; наявний `enum` з обмежень не перезаписується.
- 8.1.8: `mixed` більше не розгортається в `mixed|null`, а параметр `mixed` з `null` за замовчуванням лишається необов'язковим; приклади значень у Postman використовують типи `TypeHintResolver`.
- 8.1.9: Enum не вважаються DTO-параметрами; union-параметри використовують реальний клас для елементів `oneOf`.
- 8.1.10: Колекції DTO як параметри (див. вище); масиви з типом елемента DTO отримують DTO-схему в `items`.
- 8.1.11: `BackedEnumConvertor` викликає статичний `tryFromValue()` enum, якщо `tryFrom()` нічого не знайшов; гідрація DTO пропускається для не-масивів.
- 8.1.12: `Choice` → `enum` кладеться у відповідну гілку `oneOf`.
- 8.1.13: Схеми enum через `Ufo\DTO\Helpers\EnumsHelper`; `Choice` задає `type`. Обмеження в composer лишилося `ufo-tech/dto-transformer` ^1.1 — переконайтеся, що встановлена версія містить `EnumsHelper` (мінімальна версія в коді не вказана).
- 8.1.14: Схема enum застосовується до елементів масиву та union-параметрів (`oneOf`); рефакторинг побудови схем параметрів.

## Що врахувати при оновленні

З 8.0:

- Виконайте `composer update` через підняті мінімальні версії: `ufo-tech/dto-transformer` ^1.1, `ufo-tech/rpc-objects` ^3.1, `ufo-tech/rpc-exceptions` ^1.3.
- Якщо ви перехоплюєте порушення обмежень: бандл кидає `Ufo\RpcError\ConstraintsImposedException` (у 8.0 — `Ufo\RpcObject\Rules\Validator\ConstraintsImposedException`).
- `RpcMainConfig` має новий обов'язковий аргумент конструктора `array $sdkConfigs` (прив'язаний до `%json_rpc_sdk%`, за замовчуванням `[]`); це важливо лише при ручному створенні.
- Не залишайтеся на 8.1.1–8.1.5: вони містять описані вище регресії (спеціальні параметри виправлено в 8.1.5, схеми enum — у 8.1.6). Використовуйте 8.1.14.
- Типи з PHPDoc тепер впливають на документацію, а для `@param SomeDTO[]` — і на значення масиву під час виконання. Перевірте процедури, де PHPDoc не відповідає реальним даним.
