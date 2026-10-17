<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\EventDrivenModel\Listeners;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\EventDrivenModel\Listeners\ApiDocListener;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;

class ApiDocListenerTest extends TestCase
{
    private ArrayAdapter $pool;

    protected function setUp(): void
    {
        $this->pool = new ArrayAdapter();
    }

    private function listener(
        array $locales = ['en', 'uk'],
        string $mapHash = 'map-1',
        string $buildId = 'build-1',
        bool $debug = false,
    ): ApiDocListener {
        $config = new RpcMainConfig([], 'test');
        return new ApiDocListener(
            $this->createMock(IRpcSecurity::class),
            $config,
            $this->createMock(RouterInterface::class),
            new ServiceMap([], $config, locales: $locales),
            $this->pool,
            $mapHash,
            $buildId,
            $debug,
        );
    }

    private function request(?string $acceptLanguage = null, string $route = ApiController::API_ROUTE, ?string $ifNoneMatch = null): Request
    {
        $request = Request::create('http://localhost/rpc', server: $ifNoneMatch ? ['HTTP_IF_NONE_MATCH' => $ifNoneMatch] : []);
        $request->attributes->add(['_route' => $route, 'ver' => 'v1']);
        $request->setDefaultLocale('en');
        if ($acceptLanguage !== null) {
            $request->headers->set('Accept-Language', $acceptLanguage);
        }
        return $request;
    }

    private function requestEvent(Request $request): RequestEvent
    {
        return new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function responseEvent(Request $request, Response $response): ResponseEvent
    {
        return new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }

    /**
     * Runs the request/response listeners in kernel order around a "controller"
     * that counts how many times the documentation was generated.
     */
    private function handle(ApiDocListener $listener, Request $request, int &$generated): Response
    {
        $requestEvent = $this->requestEvent($request);
        $listener->setLocale($requestEvent);
        $listener->serveFromCache($requestEvent);
        if (!$response = $requestEvent->getResponse()) {
            $generated++;
            $response = new JsonResponse('{"locale":"'.$request->getLocale().'"}', json: true);
        }
        $responseEvent = $this->responseEvent($request, $response);
        $listener->addLanguageHeaders($responseEvent);
        $listener->storeInCache($responseEvent);
        return $responseEvent->getResponse();
    }

    // --- locale ---

    public static function acceptLanguageProvider(): array
    {
        return [
            'exact match' => ['uk', 'uk'],
            'region falls back to language' => ['uk-UA,uk;q=0.9', 'uk'],
            'quality order respected' => ['fr, uk;q=0.8, en;q=0.5', 'uk'],
            'no match falls back to default' => ['de', 'en'],
            'no header falls back to default' => [null, 'en'],
        ];
    }

    #[DataProvider('acceptLanguageProvider')]
    public function testSetsLocaleFromAcceptLanguage(?string $header, string $expected): void
    {
        $request = $this->request($header);
        $this->listener()->setLocale($this->requestEvent($request));

        $this->assertSame($expected, $request->getLocale());
    }

    public function testLocaleIgnoresNonDocsRoutes(): void
    {
        $request = $this->request('uk', 'app_home');
        $this->listener()->setLocale($this->requestEvent($request));

        $this->assertSame('en', $request->getLocale());
    }

    public function testLocaleIgnoredWithoutTranslations(): void
    {
        $request = $this->request('uk');
        $this->listener([])->setLocale($this->requestEvent($request));

        $this->assertSame('en', $request->getLocale());
    }

    public function testAddsLanguageHeadersToDocsResponse(): void
    {
        $generated = 0;
        $response = $this->handle($this->listener(debug: true), $this->request('uk'), $generated);

        $this->assertSame('uk', $response->headers->get('Content-Language'));
        $this->assertSame(['Accept-Language'], $response->getVary());
    }

    public function testNoLanguageHeadersWithoutTranslations(): void
    {
        $generated = 0;
        $response = $this->handle($this->listener([], debug: true), $this->request('uk'), $generated);

        $this->assertFalse($response->headers->has('Content-Language'));
        $this->assertSame([], $response->getVary());
    }

    // --- cache ---

    public function testSecondRequestIsServedFromCacheWithSameContentAndEtag(): void
    {
        $generated = 0;
        $first = $this->handle($this->listener(), $this->request(), $generated);
        $second = $this->handle($this->listener(), $this->request(), $generated);

        $this->assertSame(1, $generated);
        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertNotNull($first->getEtag());
        $this->assertSame($first->getEtag(), $second->getEtag());
        $this->assertSame('application/json', $second->headers->get('Content-Type'));
        $this->assertSame(['Accept-Language'], $second->getVary());
    }

    public function testMatchingIfNoneMatchReturns304WithoutGenerating(): void
    {
        $generated = 0;
        $etag = $this->handle($this->listener(), $this->request(), $generated)->getEtag();
        $response = $this->handle($this->listener(), $this->request(ifNoneMatch: $etag), $generated);

        $this->assertSame(1, $generated);
        $this->assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function testMapChangeInvalidatesCacheAndEtag(): void
    {
        $generated = 0;
        $etag = $this->handle($this->listener(mapHash: 'map-1'), $this->request(), $generated)->getEtag();
        $response = $this->handle($this->listener(mapHash: 'map-2'), $this->request(ifNoneMatch: $etag), $generated);

        $this->assertSame(2, $generated);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertNotSame($etag, $response->getEtag());
    }

    public function testContainerRebuildInvalidatesCache(): void
    {
        $generated = 0;
        $this->handle($this->listener(buildId: 'build-1'), $this->request(), $generated);
        $this->handle($this->listener(buildId: 'build-2'), $this->request(), $generated);

        $this->assertSame(2, $generated);
    }

    public function testLocalesAndDocTypesAreCachedSeparately(): void
    {
        $generated = 0;
        $en = $this->handle($this->listener(), $this->request('en'), $generated);
        $uk = $this->handle($this->listener(), $this->request('uk'), $generated);
        $this->handle($this->listener(), $this->request(route: ApiController::POSTMAN_ROUTE), $generated);

        $this->assertSame(3, $generated);
        $this->assertSame('{"locale":"uk"}', $uk->getContent());
        $this->assertNotSame($en->getEtag(), $uk->getEtag());
    }

    public function testCacheDisabledInDebug(): void
    {
        $generated = 0;
        $first = $this->handle($this->listener(debug: true), $this->request(), $generated);
        $this->handle($this->listener(debug: true), $this->request(), $generated);

        $this->assertSame(2, $generated);
        $this->assertNull($first->getEtag());
    }

    public function testCacheIgnoresNonDocsRoutes(): void
    {
        $generated = 0;
        $response = $this->handle($this->listener(), $this->request(route: 'app_home'), $generated);
        $this->handle($this->listener(), $this->request(route: 'app_home'), $generated);

        $this->assertSame(2, $generated);
        $this->assertNull($response->getEtag());
    }

    public function testErrorResponsesAreNotCached(): void
    {
        $listener = $this->listener();
        $request = $this->request();
        $listener->serveFromCache($this->requestEvent($request));
        $listener->storeInCache($this->responseEvent($request, new Response('fail', 500)));

        $this->assertFalse($this->pool->getItem($request->attributes->get(ApiDocListener::CACHE_KEY_ATTR))->isHit());
    }
}
