<?php

namespace Ufo\JsonRpcBundle\DependencyInjection\Async;

use function str_contains;
use function strtr;

/** Ім'я транспорту пари: плейсхолдери в імені або суфікс `.<group>.<version>`. */
final class AsyncTransportName
{
    public const string GROUP = '{group}';
    public const string VERSION = '{version}';

    private const string SEPARATOR = '.';

    public static function expand(string $name, string $group, string $version): string
    {
        if (!self::isTemplate($name)) {
            return $name.self::SEPARATOR.$group.self::SEPARATOR.$version;
        }

        return strtr($name, [self::GROUP => $group, self::VERSION => $version]);
    }

    public static function isTemplate(string $name): bool
    {
        return str_contains($name, self::GROUP) || str_contains($name, self::VERSION);
    }
}
