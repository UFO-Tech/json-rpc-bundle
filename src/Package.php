<?php

namespace Ufo\JsonRpcBundle;

final class Package
{
    const VERSION = 6;

    protected static string $version = '';

    public static function version(): string
    {
        if (self::$version === '') {
            $data = json_decode(file_get_contents(__DIR__.'/../composer.json'), true);
            self::$version = $data['version'] ?? self::VERSION;
        }

        return self::$version;
    }

}