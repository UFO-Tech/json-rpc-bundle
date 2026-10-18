<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\EventDrivenModel\Listeners\Traits\RpcRouteMatcherTrait;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Security\TokenHolders\HttpTokenHolder;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\JsonRpcBundle\Server\RpcLocaleNegotiator;
use Ufo\JsonRpcBundle\Server\ServiceMap\IServiceHolder;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcTokenNotSentException;
use Ufo\RpcObject\RPC\APIGroups;

use function hash;
use function json_encode;

/**
 * Everything around the API documentation routes (OpenRPC / Postman), in request order:
 * 1. access check for protected documentation (before routing);
 * 2. locale from `Accept-Language` among the locales that have RPC translations;
 * 3. cache of the generated documentation with ETag / 304.
 */
#[AsEventListener(KernelEvents::REQUEST, method: 'protectedApiDocumentation', priority: 240)]
#[AsEventListener(KernelEvents::REQUEST, method: 'setLocale', priority: 8)]
#[AsEventListener(KernelEvents::REQUEST, method: 'serveFromCache', priority: 4)]
#[AsEventListener(KernelEvents::RESPONSE, method: 'addLanguageHeaders')]
#[AsEventListener(KernelEvents::RESPONSE, method: 'storeInCache')]
class ApiDocListener
{
    use RpcRouteMatcherTrait;

    public const string CACHE_KEY_ATTR = '_ufo_rpc_docs_cache_key';
    public const string CACHE_HIT_ATTR = '_ufo_rpc_docs_cache_hit';
    public const int CACHE_TTL = 86400;

    public function __construct(
        protected IRpcSecurity $rpcSecurity,
        protected RpcMainConfig $rpcConfig,
        protected RouterInterface $router,
        protected ServiceMap $serviceMap,
        #[Autowire(service: 'rpc.cache.pool')]
        protected CacheItemPoolInterface $cache,
        #[Autowire(param: IServiceHolder::MAP_HASH)]
        protected string $mapHash,
        #[Autowire(expression: "container.hasParameter('container.build_id') ? parameter('container.build_id') : ''")]
        protected string $buildId,
        #[Autowire('%kernel.debug%')]
        protected bool $debug,
        protected ?ApiGroupContext $apiGroupContext = null,
        protected ?RpcLocaleNegotiator $localeNegotiator = null,
    ) {
        $this->localeNegotiator ??= new RpcLocaleNegotiator($this->serviceMap);
    }

    /**
     * @throws RpcTokenNotSentException
     * @throws RpcInvalidTokenException
     */
    public function protectedApiDocumentation(RequestEvent $event): void
    {
        if (!$this->rpcConfig->securityConfig->protectedDoc) return;

        $request = $event->getRequest();

        if (!$request->isMethod(Request::METHOD_GET)) return;

        $route = $this->matchRoute($request);
        if (!ApiController::isDocsRoute($route)) return;
        $this->apiGroupContext?->setGroup(
            $route[ApiGroupContext::ROUTE_GROUP] ?? APIGroups::DEFAULT_GROUP,
            $route[ApiGroupContext::ROUTE_NAME_PREFIX] ?? null
        );

        $this->rpcSecurity->setTokenHolder(new HttpTokenHolder($this->rpcConfig, $request));
        $this->rpcSecurity->isValidDocRequest();
    }

    /**
     * Runs after RouterListener (32) and LocaleListener (16) so the route is known
     * and the locale is not overwritten.
     */
    public function setLocale(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$this->isDocsRequest($request)) return;

        $this->localeNegotiator->applyLocale($request);
    }

    public function addLanguageHeaders(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$this->isDocsRequest($request)) return;

        $this->localeNegotiator->addLanguageHeaders($request, $event->getResponse());
    }

    /**
     * The cache key covers everything the output depends on: route, version, `info`, API group, host, locale,
     * the service map fingerprint (changes with any method or map change) and the container build id
     * (changes on every container rebuild). Disabled in debug: DTO classes are not tracked by the container,
     * so a DTO-only change would otherwise serve stale documentation.
     */
    public function serveFromCache(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($this->debug || !$request->isMethod(Request::METHOD_GET) || !$this->isDocsRequest($request)) return;

        $key = $this->cacheKey($request);
        $request->attributes->set(self::CACHE_KEY_ATTR, $key);

        $notModified = new Response();
        $notModified->setEtag($key);
        if ($notModified->isNotModified($request)) {
            $request->attributes->set(self::CACHE_HIT_ATTR, true);
            $event->setResponse($notModified);
            return;
        }

        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            $request->attributes->set(self::CACHE_HIT_ATTR, true);
            $event->setResponse(new JsonResponse($item->get(), json: true));
        }
    }

    public function storeInCache(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$key = $request->attributes->get(self::CACHE_KEY_ATTR)) return;

        $response = $event->getResponse();
        if ($response->getStatusCode() !== Response::HTTP_OK) return;

        $response->setEtag($key);
        if ($request->attributes->get(self::CACHE_HIT_ATTR)) return;

        $item = $this->cache->getItem($key);
        $item->set($response->getContent());
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);
    }

    protected function isDocsRequest(Request $request): bool
    {
        return ApiController::isDocsRoute($request->attributes->all());
    }

    protected function cacheKey(Request $request): string
    {
        return 'rpc_docs_'.hash('xxh128', json_encode([
            ApiController::routeFromArray($request->attributes->all()),
            $request->attributes->get('ver'),
            $request->query->has('info'),
            $this->apiGroupContext?->getGroup() ?? APIGroups::DEFAULT_GROUP,
            $request->getSchemeAndHttpHost().$request->getBaseUrl(),
            $request->getLocale(),
            $this->mapHash,
            $this->buildId,
        ]));
    }
}
