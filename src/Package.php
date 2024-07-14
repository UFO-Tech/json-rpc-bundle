<?php

namespace Ufo\JsonRpcBundle;

use Composer\InstalledVersions;

use function file_get_contents;
use function json_decode;


final class Package
{
    const string BUNDLE_NAME = 'ufo-tech/json-rpc-bundle';
    const string SPECIFICATION = 'https://www.jsonrpc.org/specification';

    protected const array PACKAGES = [
        'rpc-objects',
        'json-rpc-sdk-bundle',
        'json-rpc-client-sdk',
        'rpc-exceptions',
    ];

    protected static array $composerProject = [];
    protected static array $composerBundle = [];
    protected static ?string $description = null;
    protected static ?string $homepage = null;

    public static function bundleName(): string
    {
        return Package::fromComposer('name') ?? Package::BUNDLE_NAME;
    }

    public static function ufoEnvironment(): array
    {
        $env = [
            'env' => $_ENV['APP_ENV'] ?? '?',
        ];
        foreach (self::PACKAGES as $package) {
            $env = [
                ...$env,
                ...self::getEnvVersion($package)
            ];
        }
        return $env;
    }

    private static function getEnvVersion(string $name): array
    {
        $res = [];
        if (InstalledVersions::isInstalled('ufo-tech/' . $name)) {
            $res = [$name => InstalledVersions::getPrettyVersion('ufo-tech/' . $name)];
        }
        return $res;
    }

    public static function version(): string
    {
        return InstalledVersions::getPrettyVersion(self::BUNDLE_NAME);
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
        return InstalledVersions::getRootPackage()['install_path'];
    }
}
