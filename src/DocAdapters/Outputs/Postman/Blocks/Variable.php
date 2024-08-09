<?php

namespace Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks;

use function uniqid;

readonly class Variable implements IPostmanBlock
{
    public function __construct(
        public string $key,
        public string $value,
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
        ];
    }

}