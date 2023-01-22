# ufo-cms/json-rpc-bundle 4
![Ukraine](https://img.shields.io/badge/%D0%A1%D0%BB%D0%B0%D0%B2%D0%B0-%D0%A3%D0%BA%D1%80%D0%B0%D1%97%D0%BD%D1%96-yellow?labelColor=blue)

JSON-RPC 2.0 server for Symfony v.6.*

### About this package

Package for easy api creation using laminas json-rpc server

![License](https://img.shields.io/badge/license-MIT-green?labelColor=7b8185) ![Size](https://img.shields.io/github/repo-size/ufo-cms/json-rpc-bundle?label=Size%20of%20the%20repository) ![package_version](https://img.shields.io/github/v/tag/ufo-cms/json-rpc-bundle?color=blue&label=Latest%20Version&logo=Packagist&logoColor=white&labelColor=7b8185) ![fork](https://img.shields.io/github/forks/ufo-cms/json-rpc-bundle?color=green&logo=github&style=flat)

### Environmental requirements
![php_version](https://img.shields.io/packagist/dependency-v/ufo-cms/json-rpc-bundle/php?logo=PHP&logoColor=white) ![symfony_version](https://img.shields.io/packagist/dependency-v/ufo-cms/json-rpc-bundle/symfony/framework-bundle?label=Symfony&logo=Symfony&logoColor=white) ![laminas-json_version](https://img.shields.io/packagist/dependency-v/ufo-cms/json-rpc-bundle/laminas/laminas-json?label=laminas-json&logo=JSON&logoColor=white)

## Automatic package installation in Symfony

### Step 0 (RECOMMENDED): Configure Composer
In order for your Symfony Flex to automatically make all the necessary settings when you add a package, you need to make the following changes to your `composer.json`

```json 
// composer.json    

// ...  
  
    "extra" : {
  
        // ...  
  
        "symfony": {
  
            // ...  
  
            "endpoint": [
                "https://api.github.com/repos/UFO-CMS/recipes/contents/index.json?ref=main",
                "flex://defaults"
            ]
        }
  
        // ...  
  
    },

// ...  
  
```
More about Symfony Flex in [doc](https://symfony.com/doc/current/setup/flex_private_recipes.html)



### Step 1: Installation

From the console in the project folder, run this command to download the latest version of this package:
```console
$ composer requires ufo-cms/json-rpc-bundle 4.*
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
In the `config/packages` folder, add the `ufo_api.yaml` file with the following content
```yaml
# config/packages/ufo_json_rpc.yaml
ufo_json_rpc:
    security:
        # default parameters is enabled
```
In the future, we will configure the package here


### Step 4: Registration of routes

In the `config/routes` folder, add the `ufo_api.yaml` file with the following content
```yaml
# config/routes/ufo_json_rpc.yaml
ufo_json_rpc_bundle:
    resource: ../../vendor/ufo-cms/json-rpc-bundle/config/router.yaml
    prefix: /api
    trailing_slash_on_root: false
```
By default, the API is available at the url **http://example.com/api**
If you need to change the url, reconfigure the route as follows:
```yaml
# config/routes/ufo_json_rpc.yaml
ufo_json_rpc_bundle:
    resource: ../../vendor/ufo-cms/json-rpc-bundle/config/router.yaml
    prefix: /my_new_api_path
    trailing_slash_on_root: false
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

To enable safe mode, you must add the appropriate settings to `config/packages/ufo_api.yaml`.

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
If secure mode is enabled, your requests must contain the header specified in ***token_key_in_header***.

For example: ```Ufo-RPC-Token: ClientTokenExample```

## Integration of your RPC project with SoapUI

Your RPC project can be imported into a SoapUI application.

In order to do this, you need to import the remote project ```File -> Import Remote Project``` into SoupUI and specify the link to the xml export of your project **http://example.com/api/soapui.xml** .
In SoupUI, you will receive a ready-made project with a list of all available methods.

The URL **http://example.com/api/soapui.xml** can accept the following optional query parameters:
* ```token``` (string) client token for accessing your RPC project (it will be substituted in the SoupUI parameters of the project)
* ```show_examples``` (boolean) takes the value 1|0 - substitute example values in methods (1 - by default) or specify parameter types (0)