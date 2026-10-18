<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\ConfigService\RpcTranslationsConfig;
use Ufo\JsonRpcBundle\DependencyInjection\CompilerPass\RpcServiceMapPass;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers\ChainServiceFiller;
use Ufo\JsonRpcBundle\Server\ServiceMap\IServiceHolder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RPC;

class RpcServiceMapTranslationsTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/ufo-rpc-translations-'.bin2hex(random_bytes(8));
        mkdir($this->projectDir.'/translations', recursive: true);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->projectDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->projectDir);
    }

    private function write(string $path, string $yaml): void
    {
        $file = $this->projectDir.'/'.$path;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), recursive: true);
        }
        file_put_contents($file, $yaml);
    }

    /**
     * Runs the translation part of process() for $projectDir with optional extra paths.
     */
    private function load(RpcServiceMapPass $pass, array $paths = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $this->projectDir);
        $container->setParameter('kernel.default_locale', 'en');
        $container->setParameter(RpcTranslationsConfig::PARAMETER, $paths);
        $pass->process($container);

        return $container;
    }

    private function pass(): RpcServiceMapPass
    {
        return new class extends RpcServiceMapPass {
            protected function getServiceFiller(): ChainServiceFiller
            {
                return new ChainServiceFiller([]);
            }
        };
    }

    public function testReadTranslationsParsesLocaleFromFileName(): void
    {
        file_put_contents($this->projectDir.'/translations/rpc.uk.yaml', <<<YAML
            translate.ping:
                title: Пінг
                description: Метод пінгу
            YAML);
        file_put_contents($this->projectDir.'/translations/rpc.en.yml', <<<YAML
            translate.ping:
                title: Ping
                description: Ping method
            YAML);

        $pass = $this->pass();
        $method = new \ReflectionMethod($pass, 'readTranslations');
        $translations = $method->invoke($pass, $this->projectDir.'/translations');

        $this->assertSame([
            'uk' => ['translate.ping' => ['title' => 'Пінг', 'description' => 'Метод пінгу']],
            'en' => ['translate.ping' => ['title' => 'Ping', 'description' => 'Ping method']],
        ], $translations);
    }

    public function testReadTranslationsReturnsEmptyArrayWhenNoFilesExist(): void
    {
        $pass = $this->pass();
        $method = new \ReflectionMethod($pass, 'readTranslations');

        $this->assertSame([], $method->invoke($pass, $this->projectDir.'/translations'));
    }

    public function testApplyTranslationsFillsServiceAndDefaultLocaleIsUsedAsFallback(): void
    {
        file_put_contents($this->projectDir.'/translations/rpc.uk.yaml', <<<YAML
            translate.ping:
                title: Пінг
                description: Метод пінгу
            YAML);
        file_put_contents($this->projectDir.'/translations/rpc.en.yml', <<<YAML
            translate.ping:
                title: Ping
                description: Ping method
            YAML);

        $pass = $this->pass();
        $translations = (new \ReflectionMethod($pass, 'readTranslations'))->invoke($pass, $this->projectDir.'/translations');
        (new \ReflectionProperty($pass, 'translations'))->setValue($pass, $translations);
        (new \ReflectionProperty($pass, 'defaultLocale'))->setValue($pass, 'en');

        $pass->processServiceMap(new \ReflectionClass(TranslatedProcedure::class));
        $map = (new \ReflectionProperty($pass, 'serviceMap'))->getValue($pass);
        $service = $map['translate.ping:v1'];

        $this->assertSame('Пінг', $service->getTitle('uk'));
        $this->assertSame('Метод пінгу', $service->getDescription('uk'));

        // 'fr' has no translation file of its own — falls back to the default locale ('en'),
        // because the compiler pass adds it first so it becomes the service's translations[0].
        $this->assertSame('Ping', $service->getTitle('fr'));
        $this->assertSame('Ping method', $service->getDescription('fr'));
    }

    public function testReadGroupTranslationsSplitsGroupAndLocaleByLastSegment(): void
    {
        $this->write('translations/rpc/bfa.uk.yaml', "translate.ping:\n    title: BFA\n");
        $this->write('translations/rpc/partner.v2.en.yml', "translate.ping:\n    title: Partner\n");

        $pass = $this->pass();
        $translations = (new \ReflectionMethod($pass, 'readGroupTranslations'))->invoke($pass, $this->projectDir.'/translations');
        ksort($translations);

        $this->assertSame([
            'bfa' => ['uk' => ['translate.ping' => ['title' => 'BFA']]],
            'partner.v2' => ['en' => ['translate.ping' => ['title' => 'Partner']]],
        ], $translations);
    }

    public function testGroupOverridesBaseFieldByFieldAndWinsOverLanguage(): void
    {
        $this->write('translations/rpc.en.yaml', "translate.ping:\n    title: Ping\n    description: Ping method\n");
        $this->write('translations/rpc.uk.yaml', "translate.ping:\n    title: Пінг\n    description: Метод пінгу\n");
        $this->write('translations/rpc/bfa.uk.yaml', "translate.ping:\n    title: Пінг BFA\n");
        $this->write('translations/rpc/bff.en.yaml', "translate.ping:\n    title: Ping BFF\n");

        $pass = $this->pass();
        $this->load($pass);
        $pass->processServiceMap(new \ReflectionClass(TranslatedProcedure::class));
        $service = (new \ReflectionProperty($pass, 'serviceMap'))->getValue($pass)['translate.ping:v1'];
        $forGroup = fn(string $group): Service => (new \ReflectionMethod($pass, 'translatedForGroup'))->invoke($pass, $service, $group);

        // Only the title is overridden; the description comes from the base translation.
        $this->assertSame('Пінг BFA', $forGroup('bfa')->getTitle('uk'));
        $this->assertSame('Метод пінгу', $forGroup('bfa')->getDescription('uk'));
        $this->assertSame('Ping', $forGroup('bfa')->getTitle('en'));

        // The group has only the default locale: it wins over the base translation in the client's language.
        $this->assertSame('Ping BFF', $forGroup('bff')->getTitle('uk'));
        $this->assertSame('Метод пінгу', $forGroup('bff')->getDescription('uk'));

        // Groups without overrides and the shared service keep the base translation.
        $this->assertSame($service, $forGroup('general'));
        $this->assertSame('Пінг', $service->getTitle('uk'));
    }

    public function testApplicationOverridesExtensionPaths(): void
    {
        $this->write('extension/rpc.en.yaml', "translate.ping:\n    title: Extension\n    description: From extension\n");
        $this->write('extension/rpc/bfa.en.yaml', "translate.ping:\n    title: Extension BFA\n");
        $this->write('translations/rpc/bfa.en.yaml', "translate.ping:\n    title: App BFA\n");

        $pass = $this->pass();
        $this->load($pass, ['%kernel.project_dir%/extension/']);
        $pass->processServiceMap(new \ReflectionClass(TranslatedProcedure::class));
        $service = (new \ReflectionProperty($pass, 'serviceMap'))->getValue($pass)['translate.ping:v1'];
        $bfa = (new \ReflectionMethod($pass, 'translatedForGroup'))->invoke($pass, $service, 'bfa');

        $this->assertSame('Extension', $service->getTitle('en'));
        $this->assertSame('App BFA', $bfa->getTitle('en'));
        $this->assertSame('From extension', $bfa->getDescription('en'));
    }

    public function testLocalesIncludeGroupFilesAndContainerTracksBothDirectories(): void
    {
        $this->write('translations/rpc.en.yaml', "translate.ping:\n    title: Ping\n");
        $this->write('translations/rpc/bfa.pl.yaml', "translate.ping:\n    title: Ping PL\n");

        $container = $this->load($this->pass());

        $this->assertSame(['en', 'pl'], $container->getParameter(IServiceHolder::LOCALES));
        $tracked = array_map(fn($resource) => (string) $resource, $container->getResources());
        $dir = $this->projectDir.'/translations';
        $this->assertContains((string) new DirectoryResource($dir, '/^rpc\.([a-zA-Z0-9_]+)\.ya?ml$/'), $tracked);
        $this->assertContains((string) new DirectoryResource($dir.'/rpc', '/^(.+)\.([a-zA-Z0-9_]+)\.ya?ml$/'), $tracked);
    }

    public function testGetLocalesPutsDefaultLocaleFirst(): void
    {
        $pass = $this->pass();
        (new \ReflectionProperty($pass, 'translations'))->setValue($pass, ['uk' => [], 'en' => [], 'pl' => []]);
        (new \ReflectionProperty($pass, 'defaultLocale'))->setValue($pass, 'en');

        $this->assertSame(['en', 'uk', 'pl'], (new \ReflectionMethod($pass, 'getLocales'))->invoke($pass));
    }
}

#[RPC\Info('translate')]
class TranslatedProcedure implements IRpcService
{
    public function ping(): string { return 'pong'; }
}
