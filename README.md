# ufo-cms/json-rpc-bundle v.4.*
JSON-RPC 2.0 server for Symfony v.6.*

A package to easily create an api using json-rpc server laminas

## Getting Started

### Step 1: Installation

From the console in the project folder, run this command to download the latest version of this package:
```console
$ composer requires ufo-cms/json-rpc-bundle 3.*
```
This command is relevant if you have installed Composer globally as described in [doc](https://getcomposer.org/doc/00-intro.md) Composer documentation.

### Step 2: Register the package

Make sure that the bundle is automatically registered in your project's `config/bundles.php' file:

```php
<?php
// config/bundles.php

return [
    // ...
    Ufo\JsonRpcBundle\UfoJsonRpcBundle::class => ['all' => true],
    // ...
];

```
### Step 3: Adding parameters

Add an empty parameters section:
In the `config/bundles` folder, add the `ufo_api.yaml` file with the following content
```yaml
# config/bundles/ufo_api.yaml
ufo_json_rpc:
    security:
        # default parameters is enabled
```
In the future, we will configure the package here


### Step 4: Registration of routes

In the `config/routes` folder, add the `ufo_api.yaml` file with the following content
```yaml
# config/routes/ufo_api.yaml
ufo_json_rpc_bundle:
    resource: "@UfoJsonRpcBundle/Resources/config/routing.yml"
```
By default, the API is available at the url **http://example.com/api**
If you need to change the url, reconfigure the route as follows:
```yaml
# config/routes/ufo_api.yaml
ufo_api_server:
     path: /my_new_api_path
     controller: UfoJsonRpcBundle:Api:server
     methods: ["GET", "POST"]
```
The API will be available at the url **http://example.com/my_new_api_path**

##Congratulations, your RPC server is ready to go!!!

### Examples of use
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
The **ping** method is set right away, you can do a POST request right away to make sure the server is working.

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

### Step 5: Adding custom procedures to the rpc server

You can easily add methods to the rpc server:

Create any class that will implement ***Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService*** interface and implement any public method in that class
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
         return static::HELLO . ', '. $name;
     }
}
```
### Step 6: Profit
Make a GET request to the API to make sure your new methods are available:

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
And make test calls to your new methods:

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

### Step 7: Security

Security is disabled by default

The package supports client key validation.

To enable safe mode, you must add the appropriate settings to `config/bundles/ufo_api.yaml`.

```yml
# config/bundles/ufo_api.yaml
ufo_json_rpc:
     security:
         protected_get: true # protect GET request
         protected_post: true # protection of POST requests
         token_key_in_header: 'Ufo-RPC-Token' # Name of the key in the header
         clients_tokens:
             - 'ClientTokenExample' # hardcoded token example. Importantly!!! Replace or delete it!
             - '%env(resolve:API_TOKEN)%e' # token example from .env.local
```
If secure mode is enabled, your requests must contain the header specified in ***token_key_in_header***.

For example: ```Ufo-RPC-Token: ClientTokenExample```

## Integration of your RPC project with SoapUI

Your RPC project can be imported into a SoapUI application.

In order to do this, you need to import the remote project ```File -> Import Remote Project``` into SoupUI and specify the link to the xml export of your project **http://example.com/api/soapui.xml** .
In SoupUI, you will receive a ready-made project with a list of all available methods.

The URL **http://example.com/api/soapui.xml** can accept the following optional query parameters:
* ```token``` (string) client token for accessing your RPC project (it will be substituted in the SoupUI parameters of the project)
* ```show_examples``` (boolean) takes the value 1|0 - substitute example values in methods (1 - by default) or specify parameter types (0)