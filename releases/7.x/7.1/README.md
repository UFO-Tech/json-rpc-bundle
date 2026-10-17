[Українська](README-UA.md)

# 7.1 — DTO result schemas in OpenRPC

Tags: 7.1.1. First released: 2024-07-24.

## Highlights

- New `#[RPC\ResultAsDTO]` attribute on a method: the result schema is built from the DTO's public properties.
- Nested DTOs and DTO collections (`#[RPC\ResultAsDTO(..., collection: true)]` on a property) become `$ref`s in `components/schemas`. Circular references no longer loop.
- The OpenRPC server object reports the environment and the installed `ufo-tech/*` package versions in `x-ufo.environment`.
- The serializer helpers moved to `ufo-tech/rpc-objects`.

## What's new

### `#[RPC\ResultAsDTO]` for the response schema

The bundle reflects the DTO class through `ResultAsDtoReflector` and uses its public typed properties to build an OpenRPC schema. Nullable types become `type|null` and are left out of `required`. Union types become `oneOf`. Class-typed properties become nested schemas. An `array` property marked with `ResultAsDTO(..., collection: true)` becomes an array of `$ref`. The older `#[RPC\Response]` attribute still works: it is converted to `ResultAsDTO`.

**How to use**

```php
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

final class AddressDTO
{
    public string $city;
    public ?string $street;
}

final class UserDTO
{
    public int $id;
    public string $name;
    #[RPC\ResultAsDTO(AddressDTO::class, collection: true)]
    public array $addresses;
}

#[RPC\Info(alias: 'User')]
final class UserProcedure implements IRpcService
{
    #[RPC\ResultAsDTO(UserDTO::class)]
    public function get(int $id): UserDTO { /* ... */ }

    #[RPC\ResultAsDTO(UserDTO::class, collection: true)]
    public function list(): array { /* ... */ }
}
```

### Environment info in OpenRPC

`x-ufo.environment` on the server entry contains `env` (from `APP_ENV`) and the installed versions of `rpc-objects`, `json-rpc-sdk-bundle`, `json-rpc-client-sdk` and `rpc-exceptions`, read through `Composer\InstalledVersions`. `Package::version()` now reads the bundle version the same way.

## Changes and fixes

- The OpenRPC spec version was bumped from `1.2.6` to `1.3.2`.
- `ufo:rpc:process`: the explicit "token not set" check in `CliRpcListener` was removed. The token is still validated by `isValidToken()`.
- Fixed schema generation for DTO collections.

## Upgrade notes

- `Ufo\JsonRpcBundle\Serializer\RpcErrorNormalizer` and `Ufo\JsonRpcBundle\Serializer\RpcResponseContextBuilder` were removed. Use `Ufo\RpcObject\Transformer\RpcErrorNormalizer` and `Ufo\RpcObject\Transformer\RpcResponseContextBuilder`.
- `composer-runtime-api` is now required.
- `Service::getResponseInfo()` now returns `?ResultAsDTO` instead of `?Response`.
- Clear the `prod` cache: the cached service map format changed.
