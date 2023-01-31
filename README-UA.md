# ufo-tech/json-rpc-bundle 4
![Ukraine](https://img.shields.io/badge/%D0%A1%D0%BB%D0%B0%D0%B2%D0%B0-%D0%A3%D0%BA%D1%80%D0%B0%D1%97%D0%BD%D1%96-yellow?labelColor=blue)

JSON-RPC 2.0 сервер для Symfony v.6.*

### Про цей пакет

Пакет для легкого створення api за допомогою json-rpc сервера   
![License](https://img.shields.io/badge/license-MIT-green?labelColor=7b8185) ![Size](https://img.shields.io/github/repo-size/ufo-tech/json-rpc-bundle?label=Size%20of%20the%20repository) ![package_version](https://img.shields.io/github/v/tag/ufo-tech/json-rpc-bundle?color=blue&label=Latest%20Version&logo=Packagist&logoColor=white&labelColor=7b8185) ![fork](https://img.shields.io/github/forks/ufo-tech/json-rpc-bundle?color=green&logo=github&style=flat)

### Вимоги до оточення
![php_version](https://img.shields.io/packagist/dependency-v/ufo-tech/json-rpc-bundle/php?logo=PHP&logoColor=white) ![symfony_version](https://img.shields.io/packagist/dependency-v/ufo-tech/json-rpc-bundle/symfony/framework-bundle?label=Symfony&logo=Symfony&logoColor=white) ![symfony_version](https://img.shields.io/packagist/dependency-v/ufo-tech/json-rpc-bundle/symfony/serializer?label=SymfonySerializer&logo=Symfony&logoColor=white)
# Що нового?

### Версія 4.2
- Впроваджена мультипроцесна обробка butch запитів, що зменшує час відповіді до часу найдовшого запиту
- Реалізований механізм підписки на відповідь, завдяки службовому параметру `$rpc.callback`
Request:
```json
{
    "id": "someIdForCreateWebhook",
    "method": "SomeEntityProcedure.create",
    "params": {
        "name": "test",
        "$rpc.callback": "https://mycalback.url/endpoint"
    }
}
```
Response:
```json
{
    "id": "someIdForCreateWebhook",
    "result": {
        "callback": {
            "url": "https://mycalback.url/endpoint",
            "status": true,
            "data": []
        }
    },
    "jsonrpc": "2.0"
}
```
### Версія 4.1
- Повноцінна підтримка butch запитів з можливістю передачі результатів одного запиту, до параметрів другого

Request:
```json
[
    {
        "id": "someIdForCreateWebhook",
        "method": "SomeEntityProcedure.create",
        "params":{
            "name": "test"
        }
    },
    {
        "id": "someIdForActivateWebhook",
        "method": "SomeEntityProcedure.changeStatus",
        "params":{
            "id": "@FROM:someIdForCreateWebhook(id)",
            "status": 1
        }
    }
]
```
Response:
```json
[
    {
        "id": "someIdForCreateWebhook",
        "result": {
            "id": 123
        }
    },
    {
        "id": "someIdForActivateWebhook",
        "result": "SomeEntity 123 have status 1"
    }
]
```

# Початок роботи

## Автоматичне встановлення пакету в Symfony

### Крок 0 (РЕКОМЕНДОВАНО): Налаштування Composer
Для того, щоб при додаванні пакету, ваш Symfony Flex автоматично зробив всі необхідні налаштування, потрібно внести наступні зміни в ваш `composer.json`

```json 
// composer.json    

// ...  
  
    "extra" : {
  
        // ...  
  
        "symfony": {
  
            // ...  
  
            "endpoint": [
                "https://api.github.com/repos/ufo-tech/recipes/contents/index.json?ref=main",
                "flex://defaults"
            ]
        }
  
        // ...  
  
    },

// ...  
  
```
Детально про Symfony Flex в [документації](https://symfony.com/doc/current/setup/flex_private_recipes.html)

### Крок 1: Встановлення

В консолі в теці проєкту виконайте цю команду, щоб завантажити останню версію цього пакету:
```console  
$ composer require ufo-tech/json-rpc-bundle 4.*  
```  
Ця команда актуальна якщо ви встановили Composer глобально, як описано в [документації](https://getcomposer.org/doc/00-intro.md) документации Composer.

### Крок 2: Реєстрація пакету

Переконайтесь, що пакет автоматично зареєстувався в файлі `config/bundles.php` вашого проєкту:

```php
<?php
// config/bundles.php

return [
    // ...
    Ufo\JsonRpcBundle\UfoJsonRpcBundle::class => ['all' => true],
    // ...
];

```
### Крок 3: Додавання параметрів
If you performed "Step 0", the parameters were set automatically, you can skip this step.

Otherwise, manually add the package options section.
To do this, add the file `ufo_json_rpc.yaml` with the following content to the `config/packages` folder
```yaml
# config/packages/ufo_json_rpc.yaml
ufo_json_rpc:  
    security:
        # default - security disabled
```
Надалі конфігурувати пакет ми будем тут

### Крок 4: Реєстрація маршрутів
If you performed "Step 0", the parameters were set automatically, you can skip this step.

Otherwise, manually add the route section of the package.В теку `config/routes` додайте файл `ufo_json_rpc.yaml` із наступним змістом

```yaml
# config/routes/ufo_json_rpc.yaml  
ufo_json_rpc_bundle:  
    resource: ../../vendor/ufo-tech/json-rpc-bundle/config/router.yaml 
    prefix: /api
    trailing_slash_on_root: false
```  

За замовченням, API доступно по url **http://example.com/api**  
Якщо вам потрібно змінити url, переконфігуруйте маршрут наступним чином:
```yaml
# config/routes/ufo_json_rpc.yaml  
ufo_json_rpc_bundle:  
    resource: ../../vendor/ufo-tech/json-rpc-bundle/config/router.yaml 
    prefix: /my_new_api_path
    trailing_slash_on_root: false
```  
API буде доступне по url **http://example.com/my_new_api_path**

##Вітаю, ваш RPC сервер готовий до роботи!!!

### Приклади використання

**GET** `/api`:

```json
{
    "transport": "POST",
    "envelope": "JSON-RPC-2.0",
    "contentType": "application/json",
    "SMDVersion": "2.0",
    "description": null,
    "target": "/api",
    "services": {
        "ping": {
            "envelope": "JSON-RPC-2.0",
            "transport": "POST",
            "name": "ping",
            "parameters": [],
            "returns": "string"
        }
    },
    "methods": {
        "ping": {
            "envelope": "JSON-RPC-2.0",
            "transport": "POST",
            "name": "ping",
            "parameters": [],
            "returns": "string"
        }
    }

}
```
Метод **ping** встановлений одразу, ви можете одразу виконати POST запит, щоб переконатися, що сервер працює.

**POST** `/api`

Request:
```json
{
    "id":123,
    "method": "ping"
}
```
Response:
```json
{
    "result": "PONG",
    "id": "123"
}
```

### Крок 5: Додавання власних процедур на сервер rpc

Ви можете легко додавати методи в rpc сервер:

Створіть будь-який клас, що буде реалізовувати інтерфейс ***Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService***, і реалізуйте в цьому класі будь-який публічний метод
```php
<?php

namespace MyBundle\RpcService;

use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;

class MyRpcProcedure implements IRpcService
{
    /**
     * @var string
     */
    const HELLO = 'Hello';

    /**
     * @return string
     */
    public function sayHello()
    {
        return static::HELLO;
    }

    /**
     * @param string $name
     * @return string
     */
    public function sayHelloName($name)
    {
        return static::HELLO . ', ' . $name;
    }
}
```
### Крок 6: Профіт
Виконайте GET запит до API, щоб переконатися, що ваші нові методи доступні:

**GET** `/api`:
```json
{
    "transport": "POST",
    "envelope": "JSON-RPC-2.0",
    "contentType": "application/json",
    "SMDVersion": "2.0",
    "description": null,
    "target": "/api",
    "services": {
        "ping": {
            "envelope": "JSON-RPC-2.0",
            "transport": "POST",
            "name": "ping",
            "parameters": [],
            "returns": "string"
        },
        "MyRpcProcedure.sayHello": {
            "envelope": "JSON-RPC-2.0",
            "transport": "POST",
            "name": "ping",
            "parameters": [],
            "returns": "string"
        },
        "MyRpcProcedure.sayHelloName": {
            "envelope": "JSON-RPC-2.0",
            "transport": "POST",
            "name": "ping",
            "parameters": [
                {
                    "type": "string",
                    "name": "name",
                    "optional": false
                }
            ],
            "returns": "string"
        }
    },
    "methods": {
        "ping": {
            "envelope": "JSON-RPC-2.0",
            "transport": "POST",
            "name": "ping",
            "parameters": [],
            "returns": "string"
        },
        "MyRpcProcedure.sayHello": {
            "envelope": "JSON-RPC-2.0",
            "transport": "POST",
            "name": "ping",
            "parameters": [],
            "returns": "string"
        },
        "MyRpcProcedure.sayHelloName": {
            "envelope": "JSON-RPC-2.0",
            "transport": "POST",
            "name": "ping",
            "parameters": [
                {
                    "type": "string",
                    "name": "name",
                    "optional": false
                }
            ],
            "returns": "string"
        }
    }

}
```
І зробіть тестові виклики ваших нових методів:

**POST** `/api`
### #1
Request:
```json
{
    "id":123,
    "method": "MyRpcProcedure.sayHello"
}
```
Response:
```json
{
    "result": "Hello",
    "id": "123"
}
```
### #2
Request:
```json
{
    "id":123,
    "method": "MyRpcProcedure.sayHelloName",
    "params": {
        "operation": "Mr. Anderson"
    }
}
```
Response:
```json
{
    "result": "Hello, Mr. Anderson",
    "id": "123"
}
```

### Крок 7: Безпека

За замовченням безпека вимкнута

Пакет підтримує перевірку клієнтського ключу.

Щоб увімкнути безпечний режим, вы повинні додати відповідні налаштування в `config/packages/ufo_api.yaml`.

```yml
# config/packages/ufo_json_rpc.yaml
ufo_json_rpc:
    security:
        protected_methods: ['GET', 'POST']      # protection of GET and POST requests
        token_key_in_header: 'Ufo-RPC-Token'    # Name of the key in the header
        clients_tokens:
            - 'ClientTokenExample'              # hardcoded token example. Importantly!!! Replace or delete it!
            - '%env(resolve:UFO_API_TOKEN)%e'   # token example from .env.local
```
Якщо безпечний режим ввімкнуто, ваші запити мають містити в headers заголовок, вказанный в ***token_key_in_header***. 

Наприклад: ```Ufo-RPC-Token: ClientTokenExample``` 

## Інтеграція вашого RPC проєкту з SoapUI

Ваш RPC проєкт може бути імпортований в додаток SoapUI. 

Для того, щоб це зробити, потрібно в SoupUI імпортувати віддалений проєкт ```File -> Import Remote Project```  та вказати посилання на xml експорт вашого проєкту **http://example.com/api/soapui.xml**. 
В SoupUI ви отримаєте готовий проєкт з переліком всіх доступных методів.

URL **http://example.com/api/soapui.xml** може прийняти наступні опційні query параметри:
* ```token``` (string) клієнтський токен для доступу до вашого RPC проєкту (він підставиться в параметри SoupUI проєкту)
* ```show_examples``` (boolean) приймає значення 1|0 - підставляти приклад значень в методи (1 - за замовченням) або вказати типы параметрів (0)