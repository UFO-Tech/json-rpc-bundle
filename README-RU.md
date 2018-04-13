# ufo-cms/json-rpc-bundle v.2.0.*
JSON-RPC 2.0 сервер для Symfony

Пакет для простого использования api с помощью сервера zend json-rpc


## Начало работы

### Шаг 1: Установка пакета

Откройте консоль, перейдите в папку проекта и выполните следующую команду, чтобы загрузить последнюю стабильную версию этого пакета:
```console
$ composer require ufo-cms/json-rpc-bundle 2.0.*
```
Эта команда подразумевает, что вы установили Composer глобально, как описано в [главе установки](https://getcomposer.org/doc/00-intro.md) документации Composer.

### Шаг 2: Регистрация пакета

Убедитесь, что пакет зарегистрировался в файле `config/bundles.php` вашего проекта:

```php
<?php
// config/bundles.php

return [
    // ...
    Ufo\JsonRpcBundle\UfoJsonRpcBundle::class => ['all' => true],
    // ...
];

```
### Step 3: Добавление секции параметров

Добавьте пустую секцию параметров:

```yaml
# config/services.yaml
ufo_json_rpc:
    security:
        # default parameters is enabled

```



### Шаг 4: Регистрация путей

Зарегистрируйте маршруты этого пакета, добавив в файл маршрутизации вашего проекта следующую информацию:

```yaml
# config/routes.yaml
ufo_json_rpc_bundle:
    resource: "@UfoJsonRpcBundle/Resources/config/routing.yml"

```
По-умолчанию, API доступно по url **http://example.com/api**
Если вы хотите сменить url, переопределите путь следующим образом:
```yaml
# config/routes.yaml
ufo_api_server:
    path:     /my_new_api_path
    controller: UfoJsonRpcBundle:Api:server 
    methods: ["GET", "POST"]
```
Теперь API доступно по url **http://example.com/my_new_api_path**

Поздравляю, ваш RPC сервер готов к работе!!!

Выполнените GET запрос по URL, который вы используете для доступа к вашему серверу.

GET /api:

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
Метод **ping** доступен по-умолчанию, вы можете сразу выполнить POST запрос, чтобы убедиться, что сервер работает как следует.

POST /api

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

### Шаг 4: Добавление своих процедур на сервер rpc

Вы можете легко добавить методы в rpc сервер:

Создайте любой класс, реализующий интерфейс ***Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService***
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
Зарегистрируйте ваш класс как сервис и пометьте тегом ***rpc.service***:
```yaml
# @MyBundle/Resources/config/services.yml
services:
    rpc.my_procedure:
        class: MyBundle\RpcService\MyRpcProcedure
        tags:
            - { name: rpc.service }

```
### Шаг 5: Профит
Выполните GET запрос к API, чтобы убедиться, что ваши новые методы доступны:

GET /api:
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
И сделайте тестовые вызовы ваших новых методов:

POST /api

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

### Шаг 6: Безопасность

По-умолчанию, безопасность отключена.

Пакет поддерживает безопасность по клиентскому ключу.

Для включения безопасного режима, вы должны добавить соответствующие настройки в ```services.yaml```.

```yml
# config/services.yaml
ufo_json_rpc:
    security:
        protected_get: true                     # защитить GET запросы
        protected_post: true                    # защитить POST зпросы
        token_key_in_header: "Ufo-RPC-Token"    # Имя ключа в заголовках запроса 
        clients_tokens:
            - "ClientTokenExample"              # Пример клиентского ключа. ВАЖНО!!! Смените или удалите это!
            - "ExampleOfAnotherClientToken"     # Пример клиентского ключа. ВАЖНО!!! Смените или удалите это!
```
Если вы включили безопасный режим, ваши запросы должны содержать заголовок, указанный в параметре ***token_key_in_header***. 

Например: ```Ufo-RPC-Token: ClientTokenExample``` 

## Интеграция вашего RPC проекта с SoapUI

Ваш RPC проект может быть импортирован в приложение SoapUI. 

Для того, чтобы это сделать, достаточно в SoupUI импортировать удалёный проект ```File -> Import Remote Project```  и указать ссылку xml экспорт вашего проекта **http://example.com/api/soapui.xml**. В итоге в SoupUI вы получите готовый проект со списком всех доступных методов.

URL **http://example.com/api/soapui.xml** может принять следующие необязательные query параметры:
* ```token``` (string) клиентский токен для доступа к вашему RPC проекту (подставится в параметры SoupUI проекта)
* ```show_examples``` (boolean) допустимые значения 1|0 - подставлять пример значений в методы (1 - по-умолчанию) или указать типы параметров (0)