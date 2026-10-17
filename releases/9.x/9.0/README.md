[Українська](README-UA.md)

# 9.0 — Type system rework on dto-transformer 2: enums, unions, DTO collections

Tags: 9.0.0, 9.0.1, 9.0.2, 9.0.3, 9.0.4, 9.0.5, 9.0.6. First released: 2025-10-01.

9.0 moves the bundle to `ufo-tech/dto-transformer` ^2 and `ufo-tech/rpc-objects` ^3.3 and rebuilds how parameter and return types are turned into JSON Schema and converted at runtime. Most changes are internal, but the generated OpenRPC documentation and parameter conversion behave differently, and several extension points changed signatures.

## Highlights

- Parameter and return schemas are built from the PHP type plus the docblock type (`@param`, `@return`) in one step, so union types, nullable types, `Dto[]` and `array<string, Dto>` are described correctly in OpenRPC.
- Enums: enum values are emitted as `enum` in the documentation; `Choice` constraints with an enum callback produce the enum schema; parameters marked with `#[RPC\Param]` are converted to enum cases, also for collections.
- DTO parameters: a parameter may map to several DTO candidates (union types), and array items are converted to DTOs one by one.
- Symfony constraints are applied per type branch of a union and nested constraints (`All`, `Collection`, `Optional`) are handled by their own generators.
- DTO default values and required properties appear in the OpenRPC schema (9.0.4).

## What's new

### Unified type → JSON Schema resolution

`ParamFiller` and `ReturnFiller` now build the schema with `TypeHintResolver::typeDescriptionToJsonSchema()` (dto-transformer 2), using the docblock type when present and the native type otherwise, resolved against the procedure's `use` imports. `ParamDefinition` stores the JSON Schema (`type`) and the original PHP type (`realType`) separately. For returns, a docblock `@return` takes priority over the native return type.

**How to use** — describe the precise type in the docblock; the native hint can stay generic:

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

### Enum support

- OpenRPC: when a parameter's real type is an enum, `EnumResolver::applyEnumFqcnToJsonSchema()` adds the enum values to the schema (also inside `oneOf` / `items`).
- `IsChoice`: a `Choice` constraint whose `callback` is `[EnumClass::class, 'method']` generates the full enum schema via `EnumResolver::generateEnumSchema()`.
- Runtime: `ParamsConverterEventListener` resolves the enum class from the parameter schema (including docblock types such as `Status[]`) and converts values of parameters that carry `#[RPC\Param]`; when the attribute is marked as a collection, each item is converted.
- New `Ufo\JsonRpcBundle\ParamConvertors\EnumConvertor` (backed enums; falls back to `tryFrom()` and to a static `tryFromValue()` if the enum defines one).

**How to use:**

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

Runtime conversion of enum values goes through `#[RPC\Param]`; the exact attribute arguments depend on the installed `ufo-tech/rpc-objects` version.

### DTO parameters and collections

DTO detection moved from `ParamFiller` to `JsonSchemaNormalizer`. `Service::getParamsDto()` now returns `array<string, DtoReflector[]>` — one parameter can have several DTO candidates (e.g. `FooDto|BarDto` or `FooDto[]|BarDto[]`). At runtime the first DTO that hydrates successfully wins; for collections every item is hydrated separately and items that fail stay as raw arrays. `DtoReflector` is serializable (`__serialize` / `__unserialize`), so the service map can be cached.

### Constraint generator refactoring

`Genearator` was renamed to `Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Generator`. `IConstraintGenerator::generate()` received a third argument `?Generator $generator = null` and a `PRIORITY` constant; nested constraints (`All`, `Collection`, `Optional`) are now dispatched by the respective generators through that argument. A new trait `Generate\Traits\ConstraintApplier` applies a generator only to the schema types it supports (`getSupportedTypes()`). The `IsType` generator was removed.

**How to use** — custom generator for 9.0:

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

### DTO defaults and required fields in docs (9.0.4)

`DtoReflector` reads promoted constructor parameters and public property defaults. In the OpenRPC schema, properties with a default get `default`, and only properties without a default are listed in `required`.

## Changes and fixes

- 9.0.0 — Postman export converts the JSON Schema type back to a PHP type for example values. `Service::toArray()` includes `procedureFQCN` and `methodName`. A `null` default on a `?type` parameter now marks it optional.
- 9.0.1 — Fixed union return types: the return schema is built from the whole union (`A|B`) instead of per type; `Service::addReturn()` removed.
- 9.0.2 — Internal: the `classFQCN` key is referenced through `TypeHintResolver::CLASS_FQCN` across convertors and the OpenRPC adapter.
- 9.0.3 — `ChainParamConvertor` constructor: `JsonSchemaPropertyNormalizer` moved to the first argument (DI fix).
- 9.0.4 — Default values and required lists for DTO properties in OpenRPC.
- 9.0.5 — DTO classes inside `additionalProperties` (maps such as `array<string, Dto>`) are expanded to their schema in OpenRPC.
- 9.0.6 — `ParamsConverterEventListener` skips conversion for non-scalar values.

## Upgrade notes

- Composer: `ufo-tech/dto-transformer` ^2 (was ^1.1) and `ufo-tech/rpc-objects` ^3.3 (was ^3.1). Check your own `#[DTO]` / `#[ResultAsDTO]` usages: the bundle itself switched from `#[DTO(X::class, collection: true)]` to `#[DTOCollection(X::class)]` and from `renameKeys:` to `context: [ResultAsDTO::C_RENAME_KEYS => [...]]`.
- Custom constraint generators: rename `Genearator` → `Generator`, update `generate()` to the new signature, and stop relying on `IsType`.
- Code using bundle internals: `Service::getParamDto()` removed and `getParamsDto()` returns arrays of `DtoReflector`; `Service::addReturn()` removed (9.0.1); `Service::setReturn()`, `ParamDefinition::__construct()` / `fromParamReflection()`, `JsonSchemaNormalizer` and `ChainParamConvertor` constructors changed.
- `IParamConvertor::toScalar()` return type widened to `array|string|int|float|null` (existing implementations remain compatible).
- Clear the Symfony cache after upgrading — the cached service map format changed.
