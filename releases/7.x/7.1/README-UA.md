[English](README.md)

# 7.1 — Схеми DTO-результатів в OpenRPC

Теги: 7.1.1. Перший реліз: 2024-07-24.

## Головне

- Новий атрибут методу `#[RPC\ResultAsDTO]`: схема результату будується з публічних властивостей DTO.
- Вкладені DTO і колекції DTO (`#[RPC\ResultAsDTO(..., collection: true)]` на властивості) стають `$ref` у `components/schemas`. Циклічні посилання більше не зациклюються.
- Об'єкт server в OpenRPC повідомляє середовище та версії встановлених пакетів `ufo-tech/*` у `x-ufo.environment`.
- Допоміжні класи серіалізатора переїхали в `ufo-tech/rpc-objects`.

## Що нового

### `#[RPC\ResultAsDTO]` для схеми відповіді

Бандл рефлектує клас DTO через `ResultAsDtoReflector` і будує схему OpenRPC з його публічних типізованих властивостей. Nullable-типи стають `type|null` і не потрапляють у `required`. Union-типи стають `oneOf`. Властивості з типом-класом стають вкладеними схемами. Властивість `array` з `ResultAsDTO(..., collection: true)` стає масивом `$ref`. Старий атрибут `#[RPC\Response]` ще працює: його конвертують у `ResultAsDTO`.

**Як використати**

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

### Інформація про середовище в OpenRPC

`x-ufo.environment` в описі server містить `env` (з `APP_ENV`) і встановлені версії `rpc-objects`, `json-rpc-sdk-bundle`, `json-rpc-client-sdk` та `rpc-exceptions`, прочитані через `Composer\InstalledVersions`. `Package::version()` тепер читає версію бандла так само.

## Зміни та виправлення

- Версію специфікації OpenRPC піднято з `1.2.6` до `1.3.2`.
- `ufo:rpc:process`: з `CliRpcListener` прибрано окрему перевірку «token not set». Токен і далі перевіряє `isValidToken()`.
- Виправлено генерацію схем для колекцій DTO.

## Примітки щодо оновлення

- Видалено `Ufo\JsonRpcBundle\Serializer\RpcErrorNormalizer` і `Ufo\JsonRpcBundle\Serializer\RpcResponseContextBuilder`. Використовуйте `Ufo\RpcObject\Transformer\RpcErrorNormalizer` та `Ufo\RpcObject\Transformer\RpcResponseContextBuilder`.
- Тепер потрібен `composer-runtime-api`.
- `Service::getResponseInfo()` тепер повертає `?ResultAsDTO` замість `?Response`.
- Очистіть кеш `prod`: змінився формат закешованої карти сервісів.
