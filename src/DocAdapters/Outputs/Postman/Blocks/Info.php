<?php

namespace Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks;

use function uniqid;

readonly final class Info implements IPostmanBlock
{
    public function __construct(
        public string $name,
        public string $description,
        public string $schema,
    ) {}

    public function toArray(): array
    {
        return [
            'info' => [
                'name' => $this->name,
                'description' => $this->description,
                '_postman_id' => uniqid(),
                'schema' => $this->schema,
            ]
        ];
    }

}