# ufo-cms/json-rpc-bundle
JSON-RPC 2.0 Server for Symfony

The bundle for simple usage api with zend json-rpc server


## Getting Started

### Step 1: Install the Bundle

Open a command console, enter your project directory and execute the following command to download the latest stable version of this bundle:

```console
$ composer require ufo-cms/json-rpc-bundle
```

This command requires you to have Composer installed globally, as explained in the [installation chapter](https://getcomposer.org/doc/00-intro.md) of the Composer documentation.

### Step 2: Register the Bundle

Then, register the bundle in the `app/AppKernel.php` file of your project:

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


### Step 3: Register the routes

Register this bundle's routes by adding the following to your project's routing file:

```yaml
# app/config/routing.yml
ufo_json_rpc_bundle:
    resource: "@UfoJsonRpcBundle/Resources/config/routing.yml"

```
The API is available on the url **http://example.com/api**
If you want to change the url, redefine the routing in this way:
```yaml
# app/config/routing.yml
ufo_api_server:
    path:     /my_new_api_path
    defaults: { _controller: UfoJsonRpcBundle:Api:server }
    methods: ["GET", "POST"]
```
Now the API is available on the url **http://example.com/my_new_api_path**

Congratulations, your RPC server is ready to use!!!

Execute the GET request for the URL, on which your server is accessible (default: /api):

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
The **ping** method is available by default and you can immediately execute a POST request for make sure that the server is working as it should.

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

You can easily added methods in rpc server:

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
