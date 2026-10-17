[Українська](README-UA.md)

# 4.x — JSON-RPC 2.0 server for Symfony 6

Requirements (composer.json at 4.5.2): PHP `>=8.1`, Symfony `6.*`, `laminas/laminas-json-server >=3.2`, `ufo-tech/rpc-objects 1.*`, `ufo-tech/rpc-exceptions 1.*`.

Major 4 is the rework of the bundle for PHP 8.1 and Symfony 6. Any service implementing `IRpcService` is exposed as a set of JSON-RPC methods on one endpoint; the bundle adds batch requests with result chaining, parallel batch processing in console processes, callback delivery of responses, token-based protection and a SoapUI export. The service map and dispatching are built on Laminas JSON Server.

| Version | Highlights |
|---|---|
| [4.5](4.5/README.md) | Baseline: `IRpcService` procedures, `GET` service map, batch with `@FROM:` chaining, parallel batch via `ufo:rpc:process`, `$rpc.callback`, token security, SoapUI export |

## Upgrading from the previous major

Initial public baseline. Earlier majors (1–3, 2017–2020) are not tagged in this repository and are not covered by these notes.
