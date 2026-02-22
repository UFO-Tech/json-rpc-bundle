<?php

namespace Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks;

use function uniqid;

readonly class Info implements IPostmanBlock
{
    public string $docVersion;

    public function __construct(
        public string $name,
        public string $description,
        public string $schema,
        public string $version,
        ?string $docVersion = null,
    )
    {
        $this->docVersion = $docVersion ?? uniqid();
    }

    public function toArray(): array
    {
        return [
            'info' => [
                'name' => $this->name . ' ' . $this->version . ' (' . $this->docVersion .')',
                'description' => $this->description,
                '_postman_id' => uniqid(),
                'schema' => $this->schema,
                'version' => $this->version
            ]
        ];
    }

}