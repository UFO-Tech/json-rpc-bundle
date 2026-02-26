# CLAUDE.md — json-rpc-bundle

## Project Overview

**ufo-tech/json-rpc-bundle** is a Symfony bundle (v10.0.0) that provides a JSON-RPC 2.0 API server. It handles request routing, method dispatch, security (token-based), caching, async transport, and OpenRPC documentation generation.

- PHP >= 8.3, Symfony ^7.2
- Namespace: `Ufo\JsonRpcBundle\`
- Tests: PHPUnit 10.5, located in `tests/`

## Key Architecture

### RPC Service Registration
- RPC procedures are classes implementing `IRpcService` (`src/ApiMethod/Interfaces/IRpcService.php`)
- The interface uses `#[AutoconfigureTag(IRpcService::TAG)]` (`ufo.rpc.service`)
- Services are collected by `RpcServiceMapPass` compiler pass and built into a `ServiceMap`
- Example procedure: `src/ApiMethod/PingProcedure.php`

### Request Lifecycle
1. `ApiController` (`src/Controller/ApiController.php`) receives POST requests at the RPC endpoint
2. `RpcRequestHandler` (`src/Server/RpcRequestHandler.php`) dispatches to `RpcServer`
3. `RpcServer` resolves the method from `ServiceMap`, handles argument conversion via `ParamConvertors`

### DI / Compiler Passes
- `RpcServiceMapPass` — builds the service map at compile time
- `RpcRegisterControllerArgumentLocatorsPass` — replaces Symfony's default `RegisterControllerArgumentLocatorsPass`
- Both registered in `UfoJsonRpcBundle::build()`

### Configuration (`ufo_json_rpc:`)
- `cache`: ttl, prefix
- `security`: protected_api, protected_doc, token_name, clients_tokens
- `docs`: project_name, project_description, project_version, async_dsn_info, validations.symfony_asserts
- `async`: list of transport configs (amqp, mercure, etc.)
- Config file: `install/packages/ufo_json_rpc.yaml`

## Directory Structure

```
src/
  ApiMethod/          # Built-in RPC procedures + IRpcService interface
  CliCommand/         # Symfony console commands
  ConfigService/      # Config value objects (RpcMainConfig, RpcSecurityConfig, etc.)
  Controller/         # ApiController (single RPC endpoint)
  DependencyInjection/
    CompilerPass/     # RpcServiceMapPass, RpcRegisterControllerArgumentLocatorsPass
    Configuration.php
    UfoJsonRpcExtension.php
  DocAdapters/        # OpenRPC doc generation
  EventDrivenModel/   # Event system for async/hooks
  Exceptions/         # Bundle-specific exceptions
  Locker/             # Symfony Lock integration
  ParamConvertors/    # Automatic parameter type conversion
  Security/           # Token-based API security
  Server/             # RpcServer, RpcRequestHandler, ServiceMap, ArgumentResolver
  Validations/        # Symfony Validator integration
tests/
  Unit/               # Unit tests
install/packages/     # Default bundle config template
config/router.yaml    # Bundle route definition
```

## Running Tests

```bash
vendor/bin/phpunit
```

## Docker / Make

```bash
make up-d         # Start Docker in background
make composer-i   # composer install inside container
make exec         # Open shell in PHP container
```

Requires `.env.local` with `PROJECT_NAME` set.

## Conventions

- RPC procedures: implement `IRpcService`, use `#[RPC\Info("...")]` attribute for docs
- Param conversion is automatic via `ParamConvertors`
- Token passed via HTTP header `Ufo-RPC-Token` (configurable)
- Route format: JSON only (`format: json` in router)
