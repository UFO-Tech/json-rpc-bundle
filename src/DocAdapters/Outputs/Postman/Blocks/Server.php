<?php

namespace Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks;

use function parse_url;

use const PHP_URL_SCHEME;

readonly final class Server implements IPostmanBlock
{
    public function __construct(
        public string $raw,
    ) {
    }

    public function toArray(): array
    {
        $parse = parse_url($this->raw);
        return [
            'raw' => $this->raw,
            ...$parse
        ];
    }

}