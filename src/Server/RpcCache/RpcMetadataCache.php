<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Server\RpcCache;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

use function hash;

/**
 * Cache for metadata derived from code (hydrated services, reflected DTO formats), kept in cache.system:
 * opcache-backed PHP files in prod, cleared by cache:clear. Keys include the container build id,
 * so nothing survives a container rebuild. Disabled in debug: DTO classes are not tracked by the
 * container, so a code change could otherwise keep stale metadata. A cache failure never breaks
 * the request: the value is built as without the cache.
 */
class RpcMetadataCache
{
    public const string KEY_PREFIX = 'ufo_rpc.';

    public function __construct(
        #[Autowire(service: 'cache.system')]
        protected ?CacheItemPoolInterface $pool = null,
        #[Autowire(expression: "container.hasParameter('container.build_id') ? parameter('container.build_id') : ''")]
        protected string $buildId = '',
        #[Autowire('%kernel.debug%')]
        protected bool $debug = false,
    ) {}

    /**
     * @template T of object
     * @param class-string<T> $type expected class of the cached value; anything else is rebuilt
     * @param callable(): T $build
     * @return T
     */
    public function get(string $key, string $type, callable $build): object
    {
        if ($this->debug || !$this->pool) {
            return $build();
        }

        try {
            $item = $this->pool->getItem(static::KEY_PREFIX.hash('xxh128', $key.'|'.$this->buildId));
            if ($item->isHit() && ($cached = $item->get()) instanceof $type) {
                return $cached;
            }
        } catch (Throwable) {
            return $build();
        }

        $value = $build();
        try {
            // A value that cannot be exported (e.g. holds a closure) is simply not cached.
            $this->pool->save($item->set($value));
        } catch (Throwable) {}

        return $value;
    }
}
