<?php

namespace Ufo\JsonRpcBundle\DocAdapters\Outputs\OpenRpc;

use PSX\OpenAPI\Contact;
use PSX\OpenAPI\Info;
use PSX\OpenAPI\License;
use PSX\OpenAPI\Schemas;
use PSX\OpenAPI\Tag;
use PSX\OpenRPC\Components;
use PSX\OpenRPC\ContentDescriptor;
use PSX\OpenRPC\Error;
use PSX\OpenRPC\Method;
use PSX\OpenRPC\OpenRPC;

class OpenRpcSpecBuilder
{
    public const string OPEN_RPC_VER = "1.3.2";

    protected OpenRPC $openRPC;

    protected array $servers = [];

    protected array $methods = [];

    protected function __construct(string $openRpcVersion)
    {
        $this->openRPC = new OpenRPC();
        $this->openRPC->setOpenrpc($openRpcVersion);
    }

    public static function createBuilder(
        string $title,
        string $description = '',
        string $apiVersion = 'latest',
        string $openRpcVersion = self::OPEN_RPC_VER,
        ?string $licenseName = null,
        ?string $contactName = null,
        ?string $contactLink = null,
    ): static {
        $builder = new static($openRpcVersion);
        $license = new License();
        $license->setName($licenseName);
        $info = new Info();
        $info->setTitle($title);
        $info->setDescription($description);
        $info->setVersion($apiVersion);
        $info->setLicense($license);

        $contact = new Contact();
        $contact->setName($contactName);
        $contact->setUrl($contactLink);
        $info->setContact($contact);

        $builder->getOpenRPC()->setInfo($info);

        return $builder;
    }

    public function addServer(
        string $url,
        string $envelop,
        array $transports,
        ?string $name = UfoRpcServer::NAME,
        array $rpcEnv = []
    ): static
    {
        $server = new UfoRpcServer($envelop, $name, $transports, $rpcEnv);
        $server->setUrl($url);
        $this->servers[] = $server;

        return $this;
    }

    public function buildMethod(
        string $name,
        string $summary,
        bool $deprecated = false
    ): Method {
        $method = new Method();
        $method->setName($name);
        if ($deprecated) {
            $method->setDeprecated($deprecated);
        }
        $method->setDeprecated($deprecated);
        $method->setSummary($summary);
        $method->setParams([]);

        $this->methods[] = $method;
        return $method;
    }

    public function buildParam(
        Method $method,
        string $name,
        string $description,
        bool $required = true,
        mixed $default = null,
        array $schema = [],
        ?string $assertions = null,
    ): ContentDescriptor
    {
        $parameter = new UfoRpcParameter($assertions);
        $parameter->setName($name);
        $parameter->setDescription($description);
        $parameter->setRequired($required);
        $schema = [
            ...(!$required? ['default' => $default] : []),
            ...$schema
        ];
        $parameter->setSchema((object) $schema);

        $parameter->toRecord()->put('x-ufo-assertions', $assertions);

        $params = $method->getParams();
        $params[] = $parameter;
        $method->setParams($params);
        return $parameter;
    }

    public function buildResult(
        Method $method,
        string $name,
        string $description,
        array $schema,
    ): ContentDescriptor
    {
        $result = new ContentDescriptor();
        $result->setName($name);
        $result->setDescription($description);
        $result->setSchema((object) $schema);
        $method->setResult($result);
        return $result;
    }

    public function buildError(Method $method, int $code, string $message): Error
    {
        $error = new Error();
        $error->setCode($code);
        $error->setMessage($message);

        $errors = $method->getErrors();
        $errors[] = $error;
        $method->setErrors($errors);
        return $error;
    }

    public function buildTag(Method $method, string $name): Tag
    {
        $tag = new Tag();
        $tag->setName($name);

        $tags = $method->getTags();
        $tags[] = $tag;
        $method->setTags($tags);
        return $tag;
    }

    public function getOpenRPC(): OpenRPC
    {
        return $this->openRPC;
    }

    public function setComponents(array $schema): self
    {
        $components = new Components();
        $schema = new Schemas($schema);
        $components->setSchemas($schema);
        $this->openRPC->setComponents($components);

        return $this;
    }

    public function build(): array
    {
        $this->openRPC->setServers($this->servers);
        $this->openRPC->setMethods($this->methods);

        return $this->openRPC->toRecord()->getAll();
    }

}