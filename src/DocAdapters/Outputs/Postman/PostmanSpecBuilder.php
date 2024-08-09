<?php

namespace Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman;

use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Header;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Info;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Method;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Server;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Variable;

use function array_map;

class PostmanSpecBuilder
{
    const string SCHEMA = 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json';

    protected PostmanSpecFiller $postmanSpecFiller;

    protected array $collection;
    protected array $folders = [];

    protected Server $server;

    protected function __construct(string $name, string $description)
    {
        $this->postmanSpecFiller = new PostmanSpecFiller(new Info($name, $description, static::SCHEMA));
    }

    public static function createBuilder(string $name, string $description = ''): static
    {
        return new static($name, $description);
    }

    public function addServer(string $url): static
    {
        $this->server = new Server($url);
        return $this;
    }

    public function buildMethod(string $name, string $description, array $headers = [], string $folder = ''): Method
    {
        $headers = array_map(fn(array $header) => new Header($header['key'], $header['value']), $headers);
        $method = new Method(
            $name,
            $description,
            $headers,
            $this->server
        );

        if ($folder) {
            $this->postmanSpecFiller->addToFolder($folder, $method);
        } else {
            $this->postmanSpecFiller->addMethod($method);
        }

        return $method;
    }

    public function buildParam(
        Method $method,
        array $parameter,
    ): void
    {
        $method->addParam($parameter);
    }

    public function build(): array
    {
        return $this->postmanSpecFiller->toArray();
    }

    public function addVariable(string $key, string $value): Variable
    {
        $var = new Variable($key, $value);
        $this->postmanSpecFiller->addVariable($key, $var);
        return $var;
    }

}
