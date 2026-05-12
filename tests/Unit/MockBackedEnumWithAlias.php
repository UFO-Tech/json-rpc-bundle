<?php

namespace Ufo\JsonRpcBundle\Tests\Unit;

enum MockBackedEnumWithAlias: string
{
    case A = 'a';
    case B = 'b';

    public static function tryFromValue(string $value): ?self
    {
        return $value === 'alias' ? self::A : null;
    }
}

