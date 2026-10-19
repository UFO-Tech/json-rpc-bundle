# Security Policy

[Українська версія](#ukrainian)

## Supported Versions

Security fixes are released for the latest minor version only.

| Version | Supported          |
|---------|--------------------|
| 10.4.x  | :white_check_mark: |
| < 10.4  | :x:                |

10.x requires PHP 8.4+ and Symfony 7.2+ / 8. What changed between versions: [release notes](releases/README.md).

## Reporting a Vulnerability

Please **do not** open a public issue for a vulnerability. Report it privately by email: ashterix69@gmail.com.

Include, if you can:

- the affected version (`composer show ufo-tech/json-rpc-bundle`), PHP and Symfony versions;
- the relevant part of the `ufo_json_rpc` configuration (without tokens or other secrets);
- a JSON-RPC request or steps that reproduce the issue, and what an attacker gains.

The fix is released as a patch version and described in the [release notes](releases/README.md).

---

<a id="ukrainian"></a>

# Політика безпеки

## Підтримувані версії

Виправлення безпеки випускаються лише для останньої мінорної версії.

| Версія  | Підтримується      |
|---------|--------------------|
| 10.4.x  | :white_check_mark: |
| < 10.4  | :x:                |

10.x потребує PHP 8.4+ і Symfony 7.2+ / 8. Що змінювалось між версіями: [опис релізів](releases/README-UA.md).

## Повідомлення про вразливість

Будь ласка, **не** створюйте публічний issue про вразливість. Повідомте про неї приватно на пошту: ashterix69@gmail.com.

Додайте, якщо можете:

- версію бандла (`composer show ufo-tech/json-rpc-bundle`), версії PHP і Symfony;
- потрібну частину конфігурації `ufo_json_rpc` (без токенів та інших секретів);
- JSON-RPC запит або кроки, що відтворюють проблему, і що отримує зловмисник.

Виправлення виходить патч-версією й описується в [описі релізів](releases/README-UA.md).
