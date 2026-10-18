<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Server;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;

/**
 * Picks the language of translated texts from `Accept-Language`.
 * By default it chooses among the locales that have RPC translations (ServiceMap::getLocales(), the default locale
 * first); extensions with their own translations pass their locales explicitly.
 * Knows nothing about routes: the caller decides which requests it applies to.
 */
class RpcLocaleNegotiator
{
    public function __construct(
        protected ServiceMap $serviceMap,
    ) {}

    /**
     * The best match for `Accept-Language` (`uk-UA` matches `uk`); the first locale when nothing matches
     * or the header is absent; null when there are no translations at all.
     *
     * @param string[]|null $locales null — the locales with RPC translations
     */
    public function negotiate(Request $request, ?array $locales = null): ?string
    {
        $locales ??= $this->serviceMap->getLocales();
        // getPreferredLanguage() with an empty list returns the client's language as is — not what we want here.
        if (!$locales) return null;

        return $request->getPreferredLanguage($locales);
    }

    /**
     * Sets the negotiated locale on the request. Call it after Symfony's LocaleListener (priority 16),
     * otherwise that listener overwrites the locale.
     *
     * @param string[]|null $locales null — the locales with RPC translations
     */
    public function applyLocale(Request $request, ?array $locales = null): void
    {
        $locale = $this->negotiate($request, $locales);
        if ($locale === null) return;

        $request->setLocale($locale);
    }

    /**
     * `Content-Language` (the request locale) and `Vary: Accept-Language`, only when there are translations.
     *
     * @param string[]|null $locales null — the locales with RPC translations
     */
    public function addLanguageHeaders(Request $request, Response $response, ?array $locales = null): void
    {
        $locales ??= $this->serviceMap->getLocales();
        if (!$locales) return;

        $response->headers->set('Content-Language', $request->getLocale());
        $response->setVary('Accept-Language', false);
    }
}
