<?php

namespace Ufo\JsonRpcBundle\Tests\Unit;

enum MockPureEnumWithAlias
{
    case Draft;
    case Published;

    public static function tryFromValue(string $value): ?self
    {
        return $value === 'pub' ? self::Published : null;
    }
}
