[Українська](README-UA.md)

# 7.5 — Symfony 7.2 baseline and CLI/async fixes

Tags: 7.5.0, 7.5.1. First released: 2025-04-18.

## Highlights

- Symfony `^7.2` is now the minimum (7.5.1).
- `ufo:rpc:process` checks the token once, on `console.command`, and fails cleanly.
- Fixes for batch requests and for collecting async batch results.
- Cleanup of Symfony and PHP deprecations.

## Changes and fixes

- 7.5.0: fixed batch handling: `CurrentRpcRequestHolder` is set only for single (non-batch) requests.
- 7.5.1: `responseInfo` hydration from the cached service map uses `DTOTransformer`.
- 7.5.1: All `symfony/*` requirements were raised from `^7.0` to `^7.2`.
- 7.5.1: `CliRpcListener` no longer listens to `console.error`. The token check for `ufo:rpc:process` now runs only on `console.command`: if `protected_api` is on and `-t/--token` is missing or invalid, the command prints an error and exits with `FAILURE`.
- 7.5.1: `AsyncServerEventListener` now stops propagation of `rpc.async_output` once it has added the result to the batch.
- 7.5.1: `UfoJsonRpcExtension` extends `Symfony\Component\DependencyInjection\Extension\Extension` instead of the deprecated HttpKernel class. Nullable parameter types and normalizer signatures were updated for PHP 8.4 / Symfony 7.2 deprecations.

## Upgrade notes

- Requires Symfony 7.2 or newer (7.5.1).
- Custom listeners on `rpc.async_output` with a priority lower than 10 no longer run after the built-in `AsyncServerEventListener`.
