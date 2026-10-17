# JsonRpcBundle
![Ukraine](https://img.shields.io/badge/Glory-Ukraine-yellow?labelColor=blue)

[Українська](README-UA.md)

JSON-RPC 2.0 server for Symfony 7.2+ / 8 (PHP 8.4+).

>Creating RPC services for service-oriented architecture on Symfony has never been so easy.

![License](https://img.shields.io/badge/license-MIT-green?labelColor=7b8185) 
![Size](https://img.shields.io/github/repo-size/ufo-tech/json-rpc-bundle?label=Size%20of%20the%20repository) 
![package_version](https://img.shields.io/github/v/tag/ufo-tech/json-rpc-bundle?color=blue&label=Latest%20Version&logo=Packagist&logoColor=white&labelColor=7b8185) 
![fork](https://img.shields.io/github/forks/ufo-tech/json-rpc-bundle?color=green&logo=github&style=flat)
![php_version](https://img.shields.io/packagist/dependency-v/ufo-tech/json-rpc-bundle/php?logo=PHP&logoColor=white) 
![symfony_version](https://img.shields.io/packagist/dependency-v/ufo-tech/json-rpc-bundle/symfony/framework-bundle?label=Symfony&logo=Symfony&logoColor=white)

```bash
composer require ufo-tech/json-rpc-bundle
```

## Key features

- **Any service becomes an API.** Implement `IRpcService` and its public methods are JSON-RPC 2.0 methods on one endpoint — [4.5](releases/4.x/4.5/README.md)
- **Self-documenting.** `GET /rpc` returns OpenRPC, `GET /rpc/postman` a Postman collection; schemas come from native types, PHPDoc, DTOs, enums and validation constraints; titles and descriptions are translated by `Accept-Language` and cached with `ETag` — [6.4](releases/6.x/6.4/README.md), [7.3](releases/7.x/7.3/README.md), [9.0](releases/9.x/9.0/README.md), [10.3](releases/10.x/10.3/README.md)
- **API versions side by side.** `/rpc`, `/rpc/v2` with `#[RPC\Info('user', version: 2, removedMethods: [...])]`; each version inherits the previous one — [10.0](releases/10.x/10.0/README.md)
- **Several APIs in one application.** Public, internal and BFF entry points via `#[RPC\APIGroups]`, each with its own methods, versions, docs and token validation — [10.2](releases/10.x/10.2/README.md)
- **Batch with dependencies.** Pass one call's result into another with `@FROM:<id>(<field>)`; run items inline (fast) or in parallel child processes with a limit — [4.5](releases/4.x/4.5/README.md), [10.3](releases/10.x/10.3/README.md)
- **Notifications and callbacks.** Requests without `id` get `204` and run after the response; `$rpc.callback` posts the result to a webhook — [9.2](releases/9.x/9.2/README.md), [10.1](releases/10.x/10.1/README.md), [10.3](releases/10.x/10.3/README.md)
- **Validation.** Symfony constraints on parameters with `#[RPC\Assertions]`, one error listing every invalid parameter — [5.2](releases/5.x/5.2/README.md), [10.1](releases/10.x/10.1/README.md)
- **Automatic parameter conversion.** DTOs, enums, dates, UUIDs and Doctrine entities by id from the method signature — [8.0](releases/8.x/8.0/README.md), [10.0](releases/10.x/10.0/README.md)
- **Caching and locks.** `#[RPC\Cache]` per parameters, `#[RPC\CacheRelation]` invalidation, `#[RPC\Lock]` against concurrent calls, shared cache pool via `cache.pool` — [6.4](releases/6.x/6.4/README.md), [7.4](releases/7.x/7.4/README.md), [7.6](releases/7.x/7.6/README.md), [10.3](releases/10.x/10.3/README.md)
- **Async and security.** Symfony Messenger transports, one token model for HTTP, CLI and async, event hooks around every call — [7.0](releases/7.x/7.0/README.md), [8.0](releases/8.x/8.0/README.md), [9.1](releases/9.x/9.1/README.md)

## Release notes

All versions with what was added and how to use it: [releases](releases/README.md). Latest: [10.3](releases/10.x/10.3/README.md).

## Documentation

Installation, configuration and usage: [JsonRpcBundle documentation](https://docs.ufo-tech.space/bin/view/docs/JsonRpcBundle/?language=en) on the [UFO-Tech](https://docs.ufo-tech.space/?language=en) portal.
