<?php

namespace Ufo\JsonRpcBundle\DocAdapters\Outputs\OpenRpc;

use cebe\openapi\spec\Response;
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
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcError\RpcRuntimeException;

class OpenRpcSpecBuilder
{
    public const string OPEN_RPC_VER = "1.3.2";

    protected OpenRPC $openRPC;

    protected array $servers = [];

    protected array $methods = [];

    /** @var array<string, mixed> */
    protected array $infoExtensions = [];

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
        array $versions = []
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

        $builder->infoExtensions['x-versions'] = $versions;

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

    public function buildError(Method $method, array $throws = []): void
    {
        $codes = [];
        $errors = $method->getErrors();
        foreach ($throws as $errorName => $exception) {
            if ($errorName === 'Throwable') {
                $exception = RpcRuntimeException::class;
            }
            try {
                throw new $exception();
            } catch (\Throwable $e) {
                if ($e instanceof $exception) {
                    try {
                        $e = AbstractRpcErrorException::fromThrowable($e, false);
                    } catch (AbstractRpcErrorException $e) {}
                } else {
                    $e = new RpcBadParamException();
                }
                if (isset($codes[$e->getCode()])) continue;
                $codes[$e->getCode()] = true;

                $error = new Error();
                $error->setCode($e->getCode());
                $error->setMessage($e->getMessage());
                $errors[] = $error;
            }
        }

        $method->setErrors($errors);
    }

    public function buildTag(Method $method, string $name, string $summary): Tag
    {
        $tag = new Tag();
        $tag->setName($name);
        $tag->setDescription($summary);

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

        $data = $this->openRPC->toRecord()->getAll();

        // normalize info to array (PSX may keep it as Info object)
        if (isset($data['info']) && $data['info'] instanceof \PSX\OpenAPI\Info) {
            $data['info'] = $data['info']->toRecord()->getAll();
        }

        if ($this->infoExtensions !== []) {
            $data['info'] ??= [];
            foreach ($this->infoExtensions as $key => $value) {
                $data['info'][$key] = $value;
            }
        }

        return $data;
    }

}