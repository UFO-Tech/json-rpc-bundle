[Українська](README-UA.md)

# 8.1 — PHPDoc-driven OpenRPC schemas, DTO collections, enum schemas

Tags: 8.1.0, 8.1.1, 8.1.2, 8.1.3, 8.1.4, 8.1.5, 8.1.6, 8.1.7, 8.1.8, 8.1.9, 8.1.10, 8.1.11, 8.1.12, 8.1.13, 8.1.14. First released: 2025-07-02.

## Highlights

- **OpenRPC schemas from PHPDoc**: `@param` / `@return` types such as `ItemDTO[]`, `array<int, ItemDTO>` or unions produce real JSON Schemas (items, `oneOf`, DTO references); short class names are resolved through the procedure's `use` imports.
- **DTO collections as parameters**: an `array` parameter documented as `@param ItemDTO[] $items` is hydrated into an array of `ItemDTO` objects.
- **Enum schemas everywhere**: `BackedEnum` parameters, DTO properties, arrays of enums and union types get `enum` values in the documentation.
- **DTO property types from PHPDoc** (`@var`, `@param`, promoted constructor params) are used in the schema.

## What's new

### Schema builder from PHPDoc (8.1.1, refined in 8.1.2–8.1.3)

The type string from a method's `@param` and `@return` tags is converted to JSON Schema (`TypeHintResolver::typeDescriptionToJsonSchema()` from `ufo-tech/dto-transformer`). Class names inside it are replaced by DTO schemas in `components`. If `@return` names a class (or an array of a class), it is used as the result format (`ResultAsDTO`). For DTO properties without an `#[RPC\DTO]` attribute, the type from the property's `@var`/`@param` tag or from the constructor `@param` of a promoted property is used.

**How to use:**

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

`GET` on the RPC endpoint now shows `items` as `array` of the `ItemDTO` schema and the result as an array of `ItemDTO`.

### DTO collections as parameters (8.1.10)

When an `array` parameter's PHPDoc names a class as item type, the parameter is registered as a DTO collection and every element is built with `DTOTransformer::fromArray()` before the call. Since 8.1.11 conversion is skipped if the incoming value is not an array.

**How to use:** see the `save(array $items)` example above — `$items` arrives as `ItemDTO[]`.

### Enum schemas (8.1.6–8.1.14)

`BackedEnum` types (including nullable/union types) get `type` and `enum` in the parameter schema; since 8.1.13 the schema is produced by `EnumsHelper::generateEnumSchema()` (`ufo-tech/dto-transformer`), since 8.1.14 it is applied to array items and to the matching `oneOf` branch. `Choice` constraints put their `enum` into the matching `oneOf` branch (8.1.12) and set `type` to `string` or `integer` (8.1.13).

**How to use:**

```php
enum Status: string { case NEW = 'new'; case PAID = 'paid'; }

/**
 * @param Status[] $statuses
 */
public function find(Status $status, array $statuses = []): array
```

### Relations in documentation (8.1.0 only)

8.1.0 added `x-ufo.relations` to the OpenRPC server block, filled from the container parameter `json_rpc_sdk` (`vendors[].name`). The output was removed again in 8.1.1 (the parameter is still read into `RpcMainConfig::$sdkVendors`, but not published) and is absent up to 8.1.14.

## Changes and fixes

- 8.1.1: Dependencies raised: `ufo-tech/dto-transformer` ^1.1, `ufo-tech/rpc-objects` ^3.1, `ufo-tech/rpc-exceptions` ^1.3. Validation uses `Ufo\RpcError\ConstraintsImposedException`. **Regression**: this release dropped several 8.0.4–8.1.0 changes — enum schemas, `XUfoValuesEnum` keys (literal `x-ufo`, `x-method`, `x-ufo-assertions` are used instead), special params in `RequestCarrier`, `x-ufo.relations`, and deleted `Ufo\JsonRpcBundle\Exceptions\ConstraintsImposedException`.
- 8.1.2: `Ufo\JsonRpcBundle\Exceptions\ConstraintsImposedException` restored; DTO detection for object parameters described in PHPDoc.
- 8.1.3: The result schema falls back to the native return type when there is no `@return`; class names in nested DTO properties, arrays and `oneOf` are resolved to DTO schemas; union-type parameters produce `oneOf`.
- 8.1.4: Parsing of DTO property PHPDoc no longer fails; `@var` is used when there is no `@param`.
- 8.1.5: `RequestCarrier::getSpecialParams()` returns special params again (fixes the 8.1.1 regression).
- 8.1.6–8.1.7: Enum schemas for parameters and DTO properties restored; works for nullable/union enum types; an existing `enum` from constraints is not overwritten.
- 8.1.8: `mixed` is no longer expanded to `mixed|null`, and a `mixed` parameter with `null` default stays optional; Postman example values use `TypeHintResolver` types.
- 8.1.9: Enums are not treated as DTO parameters; union parameters use the real class for `oneOf` entries.
- 8.1.10: DTO collection parameters (see above); array parameters with a DTO item type get the DTO schema in `items`.
- 8.1.11: `BackedEnumConvertor` falls back to the enum's static `tryFromValue()` when `tryFrom()` finds nothing; DTO hydration is skipped for non-array values.
- 8.1.12: `Choice` → `enum` placed into the matching `oneOf` branch.
- 8.1.13: Enum schemas via `Ufo\DTO\Helpers\EnumsHelper`; `Choice` sets `type`. The composer constraint stays `ufo-tech/dto-transformer` ^1.1 — make sure the installed version provides `EnumsHelper` (the minimal version is not stated in the code).
- 8.1.14: Enum schema applied to array items and union (`oneOf`) parameters; refactoring of parameter schema building.

## Upgrade notes

From 8.0:

- Run `composer update` for the raised minimums: `ufo-tech/dto-transformer` ^1.1, `ufo-tech/rpc-objects` ^3.1, `ufo-tech/rpc-exceptions` ^1.3.
- If you catch constraint violations, the bundle throws `Ufo\RpcError\ConstraintsImposedException` (8.0 used `Ufo\RpcObject\Rules\Validator\ConstraintsImposedException`).
- `RpcMainConfig` has a new required constructor argument `array $sdkConfigs` (wired to `%json_rpc_sdk%`, default `[]`); relevant only if you construct it manually.
- Do not stay on 8.1.1–8.1.5: they contain the regressions listed above (special params fixed in 8.1.5, enum schemas in 8.1.6). Use 8.1.14.
- PHPDoc types now affect the documentation and, for `@param SomeDTO[]`, the runtime value of array parameters. Check procedures whose PHPDoc does not match the real data.
