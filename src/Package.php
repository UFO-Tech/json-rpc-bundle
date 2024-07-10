<?php

namespace Ufo\JsonRpcBundle;

use function file_get_contents;
use function json_decode;
use function str_replace;

final class Package
{
    const int VERSION = 7;
    const string BUNDLE_NAME = 'ufo-tech/json-rpc-bundle';
    const string BUNDLE_PATH = 'vendor/ufo-tech/json-rpc-bundle/src';
    const string SPECIFICATION = 'https://www.jsonrpc.org/specification';

    protected static array $composerProject = [];
    protected static array $composerBundle = [];
    protected static ?string $version = null;
    protected static ?string $description = null;
    protected static ?string $homepage = null;

    public static function bundleName(): string
    {
        return Package::fromComposer('name') ?? Package::BUNDLE_NAME;
    }

    public static function version(): string
    {
        return self::$version ?? Package::fromComposer('version') ?? Package::VERSION;
    }

    public static function description(): string
    {
        return self::$description ?? Package::fromComposer('description', true) ?? '';
    }

    public static function bundleDocumentation(): string
    {
        return self::$homepage ?? Package::fromComposer('homepage') ?? '';
    }

    public static function projectLicense(): string
    {
        return Package::fromComposer('license', true) ?? 'MIT';
    }

    public static function protocolSpecification(): string
    {
        return self::SPECIFICATION;
    }

    protected static function fromComposer(string $key, bool $project = false): mixed
    {
        if (empty(self::$composerProject) || empty(self::$composerBundle)) {
            $dataP = json_decode(file_get_contents(self::projectDir().'/composer.json'), true);
            $dataB = json_decode(file_get_contents(__DIR__.'/../composer.json'), true);
            self::$composerProject = $dataP ?? [];
            self::$composerBundle = $dataB ?? [];
        }

        $pData = self::$composerProject[$key] ?? null;
        $bData = self::$composerBundle[$key] ?? null;

        return $project ? ($pData ?? $bData) : $bData;
    }

    protected static function projectDir(): string
    {
        $dir = __DIR__ . '/..';
        return str_replace(self::BUNDLE_PATH, '' , $dir);
    }
}