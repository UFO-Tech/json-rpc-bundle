# AGENTS.md — json-rpc-bundle

## Purpose
This repository contains `ufo-tech/json-rpc-bundle`, a Symfony bundle that implements a JSON-RPC 2.0 server.

## Stack
- PHP >= 8.3
- Symfony ^7.2
- PHPUnit 10.5

## Quick Start
```bash
composer install
vendor/bin/phpunit
```

## Architecture Notes
- RPC services implement `Ufo\\JsonRpcBundle\\ApiMethod\\Interfaces\\IRpcService`.
- Service discovery is done via compiler pass and collected in `ServiceMap`.
- Main request flow:
  1. `src/Controller/ApiController.php`
  2. `src/Server/RpcRequestHandler.php`
  3. `src/Server/RpcServer.php`

## Coding Rules
- Follow existing namespace/layout under `src/`.
- Keep public behavior backward-compatible unless explicitly requested.
- Add/update tests for behavior changes in `tests/`.
- Prefer small focused changes over broad refactors.

## Validation
Run before finishing changes:
```bash
vendor/bin/phpunit
```

## Useful Paths
- `src/DependencyInjection/CompilerPass/`
- `src/Server/`
- `src/ApiMethod/`
- `tests/Unit/`
- `install/packages/ufo_json_rpc.yaml`
