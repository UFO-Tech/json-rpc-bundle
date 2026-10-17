[English](README.md)

# 7.5 — Базова версія Symfony 7.2 та виправлення CLI/async

Теги: 7.5.0, 7.5.1. Перший реліз: 2025-04-18.

## Головне

- Мінімальна версія Symfony тепер `^7.2` (7.5.1).
- `ufo:rpc:process` перевіряє токен один раз, на `console.command`, і коректно завершується.
- Виправлення для batch-запитів і збирання результатів async batch.
- Прибрано deprecations Symfony та PHP.

## Зміни та виправлення

- 7.5.0: виправлено обробку batch: `CurrentRpcRequestHolder` заповнюється лише для одиночних (не batch) запитів.
- 7.5.1: гідратація `responseInfo` із закешованої карти сервісів використовує `DTOTransformer`.
- 7.5.1: усі залежності `symfony/*` піднято з `^7.0` до `^7.2`.
- 7.5.1: `CliRpcListener` більше не слухає `console.error`. Перевірка токена для `ufo:rpc:process` тепер виконується лише на `console.command`: якщо `protected_api` увімкнено, а `-t/--token` відсутній або невалідний, команда виводить помилку і завершується з `FAILURE`.
- 7.5.1: `AsyncServerEventListener` тепер зупиняє поширення `rpc.async_output` після того, як додав результат у batch.
- 7.5.1: `UfoJsonRpcExtension` наслідує `Symfony\Component\DependencyInjection\Extension\Extension` замість застарілого класу з HttpKernel. Nullable-типи параметрів і сигнатури нормалізаторів оновлено під deprecations PHP 8.4 / Symfony 7.2.

## Примітки щодо оновлення

- Потрібна Symfony 7.2 або новіша (7.5.1).
- Власні listeners на `rpc.async_output` з пріоритетом нижче 10 більше не виконуються після вбудованого `AsyncServerEventListener`.
