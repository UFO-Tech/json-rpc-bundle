<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

enum TypeHintResolver: string
{
    public static function normalizeType(string $type): string
    {
        return match ($type) {
            'any', 'mixed' => 'any',
            'arr', 'array' => 'array',
            'bool', 'true', 'boolean', 'false' => 'boolean',
            'dbl', 'double', 'float' => 'float',
            'integer', 'int' => 'integer',
            'nil', 'null', 'void' => 'null',
            'string', 'str' => 'string',
            default   => 'object'
        };
    }
}
