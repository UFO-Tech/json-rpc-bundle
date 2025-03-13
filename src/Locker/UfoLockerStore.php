<?php

namespace Ufo\JsonRpcBundle\Locker;

use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\SharedLockStoreInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class UfoLockerStore implements SharedLockStoreInterface
{
    public function __construct(
        protected CacheInterface $cache,
    ) {}

    /**
     * Stores the resource if it's not locked by someone else.
     *
     * @throws LockAcquiringException
     * @throws LockConflictedException
     */
    public function save(Key $key): void
    {
        if ($this->exists($key)) {
            throw new LockAcquiringException('Lock already exists.');
        }
        $this->cache->get($this->getCacheKey($key), function (ItemInterface $item) use ($key) {
            if ($ttl = $key->getRemainingLifetime()) {
                $item->expiresAfter($ttl);
            }
            return true;
        });
    }

    public function delete(Key $key): void
    {
        $this->cache->delete($this->getCacheKey($key));
    }

    public function exists(Key $key): bool
    {
        return $this->cache->getItem($this->getCacheKey($key))->isHit();
    }

    public function putOffExpiration(Key $key, float $ttl): void
    {
        $cacheKey = $this->getCacheKey($key);
        $this->cache->delete($cacheKey);
        $this->cache->get($cacheKey, function (ItemInterface $item) use ($ttl) {
            $item->expiresAfter((int)$ttl);
            return true;
        });
    }

    public function saveRead(Key $key): void
    {
        $this->save($key);
    }

    private function getCacheKey(Key $key): string
    {
        return 'lock_' . $key;
    }
}
