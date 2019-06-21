# ufo-cms/json-rpc-bundle v.2.1.*
JSON-RPC 2.1 Server for Symfony

The bundle for simple usage api with zend json-rpc server

## What's new?
* Added automatic processing of multi-requests (Butch Requests)

## Getting Started

### Step 1: Install the Bundle

Open a command console, enter your project directory and execute the following command to download the latest stable version of this bundle:

```console
$ composer require ufo-cms/json-rpc-bundle 2.0.*
```

This command requires you to have Composer installed globally, as explained in the [installation chapter](https://getcomposer.org/doc/00-intro.md) of the Composer documentation.

### Step 2: Register the Bundle

Then, register the bundle in the `config/bundles.php` file of your project:

```php
<?php
// config/bundles.php

return [
    // ...
    Ufo\JsonRpcBundle\UfoJsonRpcBundle::class => ['all' => true],
    // ...
];

```


### Step 3: Add configs section

Add empty config section for default values:

```yaml
# config/services.yaml
ufo_json_rpc:
    security:
        # default parameters is enabled

```

### Step 4: Register the routes

Register this bundle's routes by adding the following to your project's routing file:

```yaml
# config/routes.yaml
ufo_json_rpc_bundle:
    resource: "@UfoJsonRpcBundle/Resources/config/routing.yml"

```
The API is available on the url **http://example.com/api**
If you want to change the url, redefine the routing in this way:
```yaml
# config/routes.yaml
ufo_api_server:
    path:     /my_new_api_path
    controller: UfoJsonRpcBundle:Api:server 
    methods: ["GET", "POST"]
```
Now the API is available on the url **http://example.com/my_new_api_path**

Congratulations, your RPC server is ready to use!!!

Execute GET request on url you use to access your server.
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
The **ping** method is available by default and you can immediately execute a POST request to make sure that the server is working as it should.

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

### Step 4: Add your procedures to rpc server

You can easily add methods in rpc server:

Create any class, implement interface ***Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService***
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
Register your class as service and mark tag ***rpc.service***:
```yaml
# @MyBundle/Resources/config/services.yml
services:
    rpc.my_procedure:
        class: MyBundle\RpcService\MyRpcProcedure
        tags:
            - { name: rpc.service }

```
### Step 5: Profit
Execute GET request to the API to make sure that your new methods are available:

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
And test call your new methods:

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

### Step 6: Security
By default, security is disabled.

The bundle supports security on the client's token.

To enable safe mode, you must add the settings to the ```services.yaml``` file.

```yml
# config/services.yaml
ufo_json_rpc:
    security:
        protected_get: true                     # Protected GET requests
        protected_post: true                    # Protected POST requests
        token_key_in_header: "Ufo-RPC-Token"    # Default token key 
        clients_tokens:
            - "ClientTokenExample"              # Example client token. IMPORTANT!!! Change or remove this!
            - "ExampleOfAnotherClientToken"     # Example client token. IMPORTANT!!! Change or remove this!
```
If you enable safe mode requests must contain the header key from ***token_key_in_header*** parameter.

For example: ```Ufo-RPC-Token: ClientTokenExample```

## Importing your RPC project to the SoapUI

Your RPC project can be imported to the SoapUI application.

In order to do this, it will be enough to import the remote project to the SoapUI Application ```File -> Import Remote Project``` and specify the xml export link of your project **http://example.com/api/soapui.xml**. 
As a result, you will get a ready project with a list of all available methods in SoupUI.

URL **http://example.com/api/soapui.xml** can accept the following optional query parameters:
* `Token` (string) is a client token to access your RPC project (it is substituted to the SoupUI parameters of your project)
* `Show_examples` (boolean), which accepts values 1 or 0: to substitute an example of values ​​in methods  (1 - by default) or to specify the types of parameters (0)