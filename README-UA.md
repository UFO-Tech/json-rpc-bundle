# JsonRpcBundle
![Ukraine](https://img.shields.io/badge/%D0%A1%D0%BB%D0%B0%D0%B2%D0%B0-%D0%A3%D0%BA%D1%80%D0%B0%D1%97%D0%BD%D1%96-yellow?labelColor=blue)

[English](README.md)

JSON-RPC 2.0 сервер для Symfony 7.2+ / 8 (PHP 8.4+).

>Робити RPC сервіси для сервіс-орієнтованої архітектури на Symfony ще ніколи не було так просто.

![License](https://img.shields.io/badge/license-MIT-green?labelColor=7b8185)
 ![Size](https://img.shields.io/github/repo-size/ufo-tech/json-rpc-bundle?label=Size%20of%20the%20repository)
 ![package_version](https://img.shields.io/github/v/tag/ufo-tech/json-rpc-bundle?color=blue&label=Latest%20Version&logo=Packagist&logoColor=white&labelColor=7b8185)
 ![fork](https://img.shields.io/github/forks/ufo-tech/json-rpc-bundle?color=green&logo=github&style=flat)
 ![php_version](https://img.shields.io/packagist/dependency-v/ufo-tech/json-rpc-bundle/php?logo=PHP&logoColor=white)
 ![symfony_version](https://img.shields.io/packagist/dependency-v/ufo-tech/json-rpc-bundle/symfony/framework-bundle?label=Symfony&logo=Symfony&logoColor=white)

```bash
composer require ufo-tech/json-rpc-bundle
```

## Ключові можливості

- **Будь-який сервіс стає API.** Реалізуйте `IRpcService`, і його публічні методи стануть методами JSON-RPC 2.0 на одному ендпоінті — [4.5](releases/4.x/4.5/README-UA.md)
- **Документація будується сама.** `GET /rpc` повертає OpenRPC, `GET /rpc/postman` — колекцію Postman; схеми беруться з типів PHP, PHPDoc, DTO, enum і обмежень валідації; назви й описи перекладаються за `Accept-Language` і кешуються з `ETag` — [6.4](releases/6.x/6.4/README-UA.md), [7.3](releases/7.x/7.3/README-UA.md), [9.0](releases/9.x/9.0/README-UA.md), [10.3](releases/10.x/10.3/README-UA.md)
- **Кілька версій API поруч.** `/rpc`, `/rpc/v2` через `#[RPC\Info('user', version: 2, removedMethods: [...])]`; кожна версія успадковує попередню — [10.0](releases/10.x/10.0/README-UA.md)
- **Кілька API в одному застосунку.** Публічний, внутрішній і BFF-ендпоінти через `#[RPC\APIGroups]`, у кожного свої методи, версії, документація і перевірка токенів — [10.2](releases/10.x/10.2/README-UA.md)
- **Batch із залежностями.** Результат одного виклику передається в інший через `@FROM:<id>(<поле>)`; елементи виконуються inline (швидко) або паралельно в дочірніх процесах з лімітом — [4.5](releases/4.x/4.5/README-UA.md), [10.3](releases/10.x/10.3/README-UA.md)
- **Notifications і callbacks.** Запити без `id` отримують `204` і виконуються після відповіді; `$rpc.callback` надсилає результат на webhook — [9.2](releases/9.x/9.2/README-UA.md), [10.1](releases/10.x/10.1/README-UA.md), [10.3](releases/10.x/10.3/README-UA.md)
- **Валідація.** Обмеження Symfony на параметрах через `#[RPC\Assertions]`, одна помилка з переліком усіх некоректних параметрів — [5.2](releases/5.x/5.2/README-UA.md), [10.1](releases/10.x/10.1/README-UA.md)
- **Автоматичне перетворення параметрів.** DTO, enum, дати, UUID і сутності Doctrine за id — із сигнатури методу — [8.0](releases/8.x/8.0/README-UA.md), [10.0](releases/10.x/10.0/README-UA.md)
- **Кеш і блокування.** `#[RPC\Cache]` за параметрами, інвалідація `#[RPC\CacheRelation]`, `#[RPC\Lock]` від одночасних викликів, спільний пул кешу через `cache.pool` — [6.4](releases/6.x/6.4/README-UA.md), [7.4](releases/7.x/7.4/README-UA.md), [7.6](releases/7.x/7.6/README-UA.md), [10.3](releases/10.x/10.3/README-UA.md)
- **Async і безпека.** Транспорти Symfony Messenger, єдина модель токенів для HTTP, CLI і async, події навколо кожного виклику — [7.0](releases/7.x/7.0/README-UA.md), [8.0](releases/8.x/8.0/README-UA.md), [9.1](releases/9.x/9.1/README-UA.md)

## Опис релізів

Усі версії, що в них додано і як цим користуватися: [releases](releases/README-UA.md). Остання: [10.3](releases/10.x/10.3/README-UA.md).

## Документація

Встановлення, налаштування і використання: [документація JsonRpcBundle](https://docs.ufo-tech.space/bin/view/docs/JsonRpcBundle/?language=uk) на порталі [UFO-Tech](https://docs.ufo-tech.space/?language=uk).
