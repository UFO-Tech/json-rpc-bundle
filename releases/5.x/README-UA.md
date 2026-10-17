[English](README.md)

# 5.x — Власна карта сервісів, валідація і JSON Schema

Вимоги (composer.json на 5.2.0): PHP `>=8.1`, Symfony `^6.4`, `ufo-tech/rpc-objects 1.*`, `ufo-tech/rpc-exceptions 1.*`, `phpdocumentor/reflection-docblock ^5.3`, `symfony/expression-language ^6.3`.

Мажорна версія 5 відмовляється від Laminas і замінює його власною картою сервісів на основі рефлексії. Документація методів генерується з PHP-типів і docblock, параметри можна валідувати обмеженнями Symfony через `#[RPC\Assertions]`, а ті самі обмеження дають JSON Schema для кожного методу. Процедури можна перейменувати через `#[RPC\Info]`, а документація одного методу доступна на `GET <prefix>/method/{method}`. Експорт SoapUI видалено.

| Версія | Головне |
|---|---|
| [5.2](5.2/README-UA.md) | Прибрано Laminas; валідація `#[RPC\Assertions]` з помилками по параметрах; JSON Schema в документації; описи з docblock; аліаси `#[RPC\Info]`; endpoint `/method/{method}` |

## Оновлення з попередньої мажорної версії

- Потрібен Symfony `^6.4`; пакети `laminas/*` більше не підтягуються.
- `<prefix>/soapui.xml` і класи `SoupUi\*` видалено.
- Змінився формат документації `GET`; клієнтів, що його розбирають, треба оновити.
- Змінено точки розширення: `IFacadeRpcServer::addProcedure()` без `$argv`, `getServiceMap()` повертає `ServiceLocator`, видалено `ApiProceduresPass`, `RpcServer` більше не наслідує Laminas `Server`.
- Ключі конфігурації (`security.*`) не змінилися.

Деталі: [примітки до оновлення 5.2](5.2/README-UA.md#примітки-до-оновлення).
