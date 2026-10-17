[English](README.md)

# 9.0 — Переробка системи типів на dto-transformer 2: enum, union, колекції DTO

Теги: 9.0.0, 9.0.1, 9.0.2, 9.0.3, 9.0.4, 9.0.5, 9.0.6. Перший реліз: 2025-10-01.

9.0 переводить пакет на `ufo-tech/dto-transformer` ^2 і `ufo-tech/rpc-objects` ^3.3 та перебудовує те, як типи параметрів і результатів перетворюються на JSON Schema і конвертуються під час виконання. Більшість змін внутрішні, але згенерована OpenRPC-документація і конвертація параметрів поводяться інакше, а кілька точок розширення змінили сигнатури.

## Головне

- Схеми параметрів і результатів будуються з PHP-типу та типу з docblock (`@param`, `@return`) за один крок, тож union-типи, nullable-типи, `Dto[]` і `array<string, Dto>` коректно описуються в OpenRPC.
- Enum: значення enum виводяться як `enum` у документації; обмеження `Choice` з enum-callback дають enum-схему; параметри з `#[RPC\Param]` конвертуються в case enum, зокрема для колекцій.
- DTO-параметри: один параметр може мати кілька DTO-кандидатів (union-типи), а елементи масиву конвертуються в DTO поштучно.
- Symfony-обмеження застосовуються до кожної гілки union-типу, а вкладені обмеження (`All`, `Collection`, `Optional`) обробляються власними генераторами.
- Значення за замовчуванням і обов'язкові властивості DTO з'являються в OpenRPC-схемі (9.0.4).

## Що нового

### Єдине перетворення типу → JSON Schema

`ParamFiller` і `ReturnFiller` тепер будують схему через `TypeHintResolver::typeDescriptionToJsonSchema()` (dto-transformer 2): беруть тип із docblock, якщо він є, інакше нативний тип, і резолвлять класи за `use`-імпортами процедури. `ParamDefinition` зберігає JSON Schema (`type`) і вихідний PHP-тип (`realType`) окремо. Для результату `@return` з docblock має пріоритет над нативним типом.

**Як використати** — опишіть точний тип у docblock; нативний type hint може лишатися загальним:

```php
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

#[RPC\Info('Orders')]
class OrderProcedure implements IRpcService
{
    /**
     * @param OrderItemDto[] $items
     * @return array<string, OrderItemDto>
     */
    public function create(array $items, ?string $comment = null): array
    {
        // ...
    }
}
```

### Підтримка enum

- OpenRPC: якщо реальний тип параметра — enum, `EnumResolver::applyEnumFqcnToJsonSchema()` додає значення enum у схему (зокрема всередині `oneOf` / `items`).
- `IsChoice`: обмеження `Choice` з `callback` вигляду `[EnumClass::class, 'method']` генерує повну enum-схему через `EnumResolver::generateEnumSchema()`.
- Виконання: `ParamsConverterEventListener` визначає клас enum зі схеми параметра (включно з docblock-типами на кшталт `Status[]`) і конвертує значення параметрів, що мають `#[RPC\Param]`; якщо атрибут позначено як колекцію, конвертується кожен елемент.
- Новий `Ufo\JsonRpcBundle\ParamConvertors\EnumConvertor` (backed enum; запасні варіанти — `tryFrom()` і статичний `tryFromValue()`, якщо enum його визначає).

**Як використати:**

```php
enum OrderStatus: string
{
    case NEW = 'new';
    case PAID = 'paid';
}

/**
 * @param OrderStatus[] $statuses
 */
public function findByStatus(array $statuses): array { /* ... */ }
```

Конвертація значень у enum під час виконання йде через `#[RPC\Param]`; точні аргументи атрибута залежать від встановленої версії `ufo-tech/rpc-objects`.

### DTO-параметри та колекції

Визначення DTO перенесено з `ParamFiller` у `JsonSchemaNormalizer`. `Service::getParamsDto()` тепер повертає `array<string, DtoReflector[]>` — один параметр може мати кілька DTO-кандидатів (напр. `FooDto|BarDto` або `FooDto[]|BarDto[]`). Під час виконання перемагає перший DTO, який вдалося гідрувати; для колекцій кожен елемент гідрується окремо, а елементи, що не пройшли, лишаються сирими масивами. `DtoReflector` серіалізується (`__serialize` / `__unserialize`), тож карту сервісів можна кешувати.

### Рефакторинг генераторів обмежень

`Genearator` перейменовано на `Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Generator`. `IConstraintGenerator::generate()` отримав третій аргумент `?Generator $generator = null` і константу `PRIORITY`; вкладені обмеження (`All`, `Collection`, `Optional`) тепер обробляють відповідні генератори через цей аргумент. Новий трейт `Generate\Traits\ConstraintApplier` застосовує генератор лише до тих типів схеми, які він підтримує (`getSupportedTypes()`). Генератор `IsType` видалено.

**Як використати** — власний генератор для 9.0:

```php
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Generator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Traits\ConstraintApplier;

#[AutoconfigureTag('rpc.constraint')]
class IsMyConstraint implements IConstraintGenerator
{
    use ConstraintApplier;

    public function getSupportedClass(): string { return MyConstraint::class; }

    protected function getSupportedTypes(): array { return ['string']; }

    protected function apply(Constraint $constraint, array &$rules, ?Generator $generator = null): void
    {
        $rules['format'] = 'my-format';
    }
}
```

### Значення за замовчуванням і обов'язкові поля DTO в документації (9.0.4)

`DtoReflector` читає promoted-параметри конструктора і значення за замовчуванням публічних властивостей. В OpenRPC-схемі властивості зі значенням за замовчуванням отримують `default`, а в `required` потрапляють лише властивості без нього.

## Зміни та виправлення

- 9.0.0 — Експорт у Postman перетворює тип JSON Schema назад на PHP-тип для прикладів значень. `Service::toArray()` містить `procedureFQCN` і `methodName`. `null` за замовчуванням для параметра `?type` тепер робить його необов'язковим.
- 9.0.1 — Виправлено union-типи результату: схема будується з усього union (`A|B`), а не по одному типу; `Service::addReturn()` видалено.
- 9.0.2 — Внутрішнє: ключ `classFQCN` використовується через `TypeHintResolver::CLASS_FQCN` у конвертерах і OpenRPC-адаптері.
- 9.0.3 — Конструктор `ChainParamConvertor`: `JsonSchemaPropertyNormalizer` перенесено на перше місце (виправлення DI).
- 9.0.4 — Значення за замовчуванням і списки `required` для властивостей DTO в OpenRPC.
- 9.0.5 — DTO-класи всередині `additionalProperties` (мапи на кшталт `array<string, Dto>`) розгортаються в їхню схему в OpenRPC.
- 9.0.6 — `ParamsConverterEventListener` пропускає конвертацію нескалярних значень.

## Примітки до оновлення

- Composer: `ufo-tech/dto-transformer` ^2 (було ^1.1) і `ufo-tech/rpc-objects` ^3.3 (було ^3.1). Перевірте власні `#[DTO]` / `#[ResultAsDTO]`: сам пакет перейшов з `#[DTO(X::class, collection: true)]` на `#[DTOCollection(X::class)]` і з `renameKeys:` на `context: [ResultAsDTO::C_RENAME_KEYS => [...]]`.
- Власні генератори обмежень: перейменуйте `Genearator` → `Generator`, оновіть `generate()` до нової сигнатури й не покладайтеся на `IsType`.
- Код, що використовує внутрішні класи пакета: `Service::getParamDto()` видалено, `getParamsDto()` повертає масиви `DtoReflector`; `Service::addReturn()` видалено (9.0.1); змінилися `Service::setReturn()`, `ParamDefinition::__construct()` / `fromParamReflection()`, конструктори `JsonSchemaNormalizer` і `ChainParamConvertor`.
- Тип результату `IParamConvertor::toScalar()` розширено до `array|string|int|float|null` (наявні реалізації лишаються сумісними).
- Після оновлення очистіть кеш Symfony — формат кешованої карти сервісів змінився.
