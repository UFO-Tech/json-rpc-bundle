<?php

namespace Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks;

use function array_filter;
use function array_values;
use function explode;
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
        $data = [
            'protocol' => $parse['scheme'],
            'host' => explode('.', $parse['host']),
            'path' => array_values(array_filter(explode('/', $parse['path']))),
        ];
        if (isset($parse['port'])) {
            $data['port'] = $parse['port'];
        }
        return [
            'raw' => $this->raw,
            ...$data
        ];
    }

}