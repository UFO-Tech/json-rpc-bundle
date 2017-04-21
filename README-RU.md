# ufo-cms/json-rpc-bundle
JSON-RPC 2.0 сервер для Symfony

Пакет для простого использования api с помощью сервера zend json-rpc


## Начало работы

### Шаг 1: Установка пакета

Откройте консоль, перейдите в папку проекта и выполните следующую команду, чтобы загрузить последнюю стабильную версию этого пакета:
```console
$ composer require ufo-cms/json-rpc-bundle
```
Эта команда подразумевает, что вы установили Composer глобально, как описано в [главе установки] (https://getcomposer.org/doc/00-intro.md) документации Composer.

### Шаг 2: Регистрация пакета

Зарегистрируйте пакет в файле `app/AppKernel.php` вашего проекта:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            // ...

            new Ufo\JsonRpcBundle\UfoJsonRpcBundle(),
            // ...
        ];

        // ...
    }

    // ...
}
```


### Шаг 3: Регистрация маршрутов

Зарегистрируйте маршруты этого пакета, добавив в файл маршрутизации вашего проекта следующую информацию:

```yaml
# app/config/routing.yml
ufo_json_rpc_bundle:
    resource: "@UfoJsonRpcBundle/Resources/config/routing.yml"

```
По-умолчанию, API доступно по url **http://example.com/api**
Если вы хотите сменить url, переопределите маршрут следующим образом:
```yaml
# app/config/routing.yml
ufo_api_server:
    path:     /my_new_api_path
    defaults: { _controller: UfoJsonRpcBundle:Api:server }
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

Для включения безопасного режима, вы должны добавить соответствующие настройки в ```config.yml```.

```yml
# app/config/config.yml
ufo_json_rpc:
    security:
        protected_get: true     # защитить GET запросы
        protected_post: true    # защитить POST зпросы
        clients_tokens:
            - "ClientKeyExample"            # Пример клиентского ключа. ВАЖНО!!! Смените или удалите это!
            - "ExampleOfAnotherClientKey"   # Пример клиентского ключа. ВАЖНО!!! Смените или удалите это!
```
