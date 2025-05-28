<?php

namespace Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks;

use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;

use function array_map;
use function is_array;
use function json_encode;
use function rand;
use function uniqid;

use const JSON_PRETTY_PRINT;

final class Method implements IPostmanBlock
{
    /**
     * @param string $name
     * @param string $description
     * @param Header[] $headers
     * @param Server $url
     */
    public function __construct(
        readonly public string $name,
        readonly public string $description,
        readonly public array $headers,
        readonly public Server $url,
    ) {}

    /**
     * @var ParamDefinition[]
     */
    protected array $params = [];

    public function addParam(ParamDefinition $param): self
    {
        $this->params[] = $param;
        return $this;
    }

    protected function getRpcSignature(): array
    {
        $params = [];
        foreach ($this->params as $param) {
            $params = array_merge($params, $this->paramConvert($param));
        }

        $rpc = [
            'jsonrpc' => '2.0',
            'method' => $this->name,
        ];
        if (!empty($params)) {
            $rpc['params'] = $params;
        }
        $rpc['id'] = rand(1,10000);
        return $rpc;
    }

    protected function paramConvert(ParamDefinition $param): array
    {
        $type = $param->getType();
        if (is_array($type)) {
            $type = $type[0];
        }
        return [
            $param->name => $param->isOptional() ? $param->getDefault() : $this->exampleValueByType($type),
        ];
    }

    protected function exampleValueByType(string $type): mixed
    {
        return match ($type) {
            'int', 'float' => 0,
            'bool' => true,
            'string' => '',
            'array' => [],
            default => null,
        };
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'request' => [
                'description' => $this->description,
                'method' => 'POST',
                'header' => array_map(fn(Header $header) => $header->toArray(), $this->headers),
                'body' => [
                    'mode' => 'raw',
                    'raw' => json_encode($this->getRpcSignature(), JSON_PRETTY_PRINT),
                ],
                'url' => $this->url->toArray(),
            ],
        ];
    }

}
