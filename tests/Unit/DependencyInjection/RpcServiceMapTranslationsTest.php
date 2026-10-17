<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\DependencyInjection\CompilerPass\RpcServiceMapPass;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers\ChainServiceFiller;
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
        foreach (glob($this->projectDir.'/translations/*') as $file) {
            unlink($file);
        }
        rmdir($this->projectDir.'/translations');
        rmdir($this->projectDir);
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
        $translations = $method->invoke($pass, $this->projectDir);

        $this->assertSame([
            'uk' => ['translate.ping' => ['title' => 'Пінг', 'description' => 'Метод пінгу']],
            'en' => ['translate.ping' => ['title' => 'Ping', 'description' => 'Ping method']],
        ], $translations);
    }

    public function testReadTranslationsReturnsEmptyArrayWhenNoFilesExist(): void
    {
        $pass = $this->pass();
        $method = new \ReflectionMethod($pass, 'readTranslations');

        $this->assertSame([], $method->invoke($pass, $this->projectDir));
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
        $translations = (new \ReflectionMethod($pass, 'readTranslations'))->invoke($pass, $this->projectDir);
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
