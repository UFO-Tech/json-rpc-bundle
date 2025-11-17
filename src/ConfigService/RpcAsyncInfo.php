<?php

namespace Ufo\JsonRpcBundle\ConfigService;

use Ufo\RpcObject\RpcAsyncRequest;

readonly class RpcAsyncInfo
{
    public function __construct(
        public string $type,
        public string $name,
        public array $config = [],
    ) {}

    public static function fromArray(array $config): static
    {
        $name = $config['config']['name'] ?? RpcAsyncRequest::class;
        unset($config['config']['name']);
        return new static(
            $config['type'] ?? RpcAsyncConfig::DEFAULT_TYPE,
            $name,
            $config['config']
        );
    }
}