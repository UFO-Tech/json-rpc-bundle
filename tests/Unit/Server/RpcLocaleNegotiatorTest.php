<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\Server;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Server\RpcLocaleNegotiator;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;

class RpcLocaleNegotiatorTest extends TestCase
{
    private function negotiator(array $locales): RpcLocaleNegotiator
    {
        return new RpcLocaleNegotiator(new ServiceMap([], new RpcMainConfig([], 'test'), locales: $locales));
    }

    private function request(?string $acceptLanguage): Request
    {
        $request = Request::create('http://localhost/rpc');
        $request->setDefaultLocale('en');
        if ($acceptLanguage !== null) {
            $request->headers->set('Accept-Language', $acceptLanguage);
        }
        return $request;
    }

    public static function negotiateCases(): iterable
    {
        yield 'exact match' => [['en', 'uk'], 'uk', 'uk'];
        yield 'region falls back to language' => [['en', 'uk'], 'uk-UA,uk;q=0.9', 'uk'];
        yield 'quality order' => [['en', 'uk', 'pl'], 'de, pl;q=0.9, uk;q=0.8', 'pl'];
        yield 'unknown language gives the first locale' => [['en', 'uk'], 'de', 'en'];
        yield 'no header gives the first locale' => [['en', 'uk'], null, 'en'];
        // The first locale is the default one only when it has translations; otherwise just the first available.
        yield 'first locale is not necessarily the default' => [['uk'], 'de', 'uk'];
        yield 'no translations' => [[], 'uk', null];
    }

    #[DataProvider('negotiateCases')]
    public function testNegotiate(array $locales, ?string $header, ?string $expected): void
    {
        $this->assertSame($expected, $this->negotiator($locales)->negotiate($this->request($header)));
    }

    public function testExplicitLocalesReplaceRpcLocales(): void
    {
        $negotiator = $this->negotiator(['en', 'uk']);

        $this->assertSame('pl', $negotiator->negotiate($this->request('pl'), ['de', 'pl']));
        $this->assertNull($negotiator->negotiate($this->request('uk'), []));
    }

    public function testApplyLocaleSetsNegotiatedLocale(): void
    {
        $request = $this->request('uk-UA');
        $this->negotiator(['en', 'uk'])->applyLocale($request);

        $this->assertSame('uk', $request->getLocale());
    }

    public function testApplyLocaleKeepsLocaleWithoutTranslations(): void
    {
        $request = $this->request('uk');
        $this->negotiator([])->applyLocale($request);

        $this->assertSame('en', $request->getLocale());
    }

    public function testAddLanguageHeaders(): void
    {
        $request = $this->request('uk');
        $request->setLocale('uk');
        $response = new Response();
        $response->setVary('Accept-Encoding');

        $this->negotiator(['en', 'uk'])->addLanguageHeaders($request, $response);

        $this->assertSame('uk', $response->headers->get('Content-Language'));
        $this->assertSame(['Accept-Encoding', 'Accept-Language'], $response->getVary());
    }

    public function testNoLanguageHeadersWithoutTranslations(): void
    {
        $response = new Response();
        $this->negotiator([])->addLanguageHeaders($this->request('uk'), $response);

        $this->assertFalse($response->headers->has('Content-Language'));
        $this->assertSame([], $response->getVary());
    }
}
