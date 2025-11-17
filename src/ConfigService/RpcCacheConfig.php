<?php

namespace Ufo\JsonRpcBundle\ConfigService;

use Ufo\RpcObject\RPC\Cache;

final readonly class RpcCacheConfig
{
    const string NAME = 'cache';
    const string TTL = 'ttl';
    const string PREFIX = 'prefix';
    const string P_ADAPTER = '@cache.adapter.filesystem';
    const string P_PREFIX = 'rpc_cache';

    public string $adapter;

    public int $ttl;
    public string $prefix;

    public function __construct(array $rpcConfigs, public RpcMainConfig $parent)
    {
        $this->ttl = $rpcConfigs[self::TTL] ?? Cache::T_MINUTE;
        $this->prefix = $rpcConfigs[self::PREFIX] ?? self::P_PREFIX;
    }

}