# ufo-cms/json-rpc-bundle v.3.*
JSON-RPC 2.0 сервер для Symfony

Пакет для легкого створення api за допомогою сервера laminas json-rpc

## Початок роботи

### Крок 1: Встановлення

В консолі в теці проєкту виконайте цю команду, щоб завантажити останню версію цього пакету:
```console
$ composer require ufo-cms/json-rpc-bundle 3.*
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

Добавьте пустую секцию параметров:
В теку `config/bundles` додайте файл `ufo_api.yaml` із наступним змістом
```yaml
# config/bundles/ufo_api.yaml
ufo_json_rpc:
    security:
        # default parameters is enabled

```
В подальшому конфігурувати пакет ми будем тут


### Крок 4: Реєстрація маршрутів

В теку `config/routes` додайте файл `ufo_api.yaml` із наступним змістом

```yaml
# config/routes/ufo_api.yaml
ufo_json_rpc_bundle:
    resource: "@UfoJsonRpcBundle/Resources/config/routing.yml"
```

За замовченням, API доступно по url **http://example.com/api**
Якщо вам потрібно змінити url, переконфігуруйте маршрут наступним чином:
```yaml
# config/routes/ufo_api.yaml
ufo_api_server:
    path:     /my_new_api_path
    controller: UfoJsonRpcBundle:Api:server 
    methods: ["GET", "POST"]
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
Якщо у вас відключений autowire, зареєструйте ваш клас як сервіс та маркуйте його тегом ***ufo.rpc.service***:
```yaml
# @MyBundle/Resources/config/services.yml
services:
    rpc.my_procedure:
        class: MyBundle\RpcService\MyRpcProcedure
        tags:
            - { name: ufo.rpc.service }

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

Щоб увімкнути безпечний режим, вы повинні додати відповідні налаштування в `config/bundles/ufo_api.yaml`.

```yml
# config/bundles/ufo_api.yaml
ufo_json_rpc:
    security:
        protected_get: true                     # захист GET запиту
        protected_post: true                    # захист POST запитів
        token_key_in_header: 'Ufo-RPC-Token'    # Назва ключу в хедері 
        clients_tokens:
            - 'ClientTokenExample'              # приклад хардкодного токену. Важливо!!! Замініть, або видаліть це!
            - '%env(resolve:API_TOKEN)%e'       # приклад токену з .env.local 
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