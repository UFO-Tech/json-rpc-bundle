<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\RpcCache;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Ufo\JsonRpcBundle\Server\RpcCache\RpcMetadataCache;

class RpcMetadataCacheTest extends TestCase
{
    public function testValueIsBuiltOnceAndThenReadFromPool(): void
    {
        $pool = new ArrayAdapter();
        $builds = 0;
        $build = function () use (&$builds) { $builds++; return new \ArrayObject(['v' => 1]); };

        (new RpcMetadataCache($pool, 'b1'))->get('k', \ArrayObject::class, $build);
        $value = (new RpcMetadataCache($pool, 'b1'))->get('k', \ArrayObject::class, $build);

        $this->assertSame(1, $builds);
        $this->assertSame(['v' => 1], $value->getArrayCopy());
    }

    public function testContainerRebuildChangesKey(): void
    {
        $pool = new ArrayAdapter();
        $builds = 0;
        $build = function () use (&$builds) { $builds++; return new \ArrayObject(); };

        (new RpcMetadataCache($pool, 'b1'))->get('k', \ArrayObject::class, $build);
        (new RpcMetadataCache($pool, 'b2'))->get('k', \ArrayObject::class, $build);

        $this->assertSame(2, $builds);
    }

    public function testDisabledInDebugAndWithoutPool(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->never())->method('getItem');

        (new RpcMetadataCache($pool, 'b1', debug: true))->get('k', \ArrayObject::class, fn() => new \ArrayObject());
        $value = (new RpcMetadataCache(null))->get('k', \ArrayObject::class, fn() => new \ArrayObject(['x']));

        $this->assertSame(['x'], $value->getArrayCopy());
    }

    public function testValueOfUnexpectedTypeIsRebuilt(): void
    {
        $pool = new ArrayAdapter();
        $cache = new RpcMetadataCache($pool, 'b1');
        $cache->get('k', \ArrayObject::class, fn() => new \ArrayObject());

        $value = $cache->get('k', \SplObjectStorage::class, fn() => new \SplObjectStorage());

        $this->assertInstanceOf(\SplObjectStorage::class, $value);
    }

    public function testPoolFailureDoesNotBreakTheCaller(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willThrowException(new \RuntimeException('cache down'));

        $value = (new RpcMetadataCache($pool, 'b1'))->get('k', \ArrayObject::class, fn() => new \ArrayObject(['ok']));

        $this->assertSame(['ok'], $value->getArrayCopy());
    }

    public function testUnexportableValueIsReturnedButNotCached(): void
    {
        $dir = sys_get_temp_dir().'/ufo-rpc-meta-'.bin2hex(random_bytes(6));
        $pool = new PhpFilesAdapter('', 0, $dir);
        $builds = 0;
        $build = function () use (&$builds) {
            $builds++;
            $object = new \ArrayObject();
            $object['closure'] = fn() => 1;   // closures cannot be exported
            return $object;
        };

        $first = (new RpcMetadataCache($pool, 'b1'))->get('k', \ArrayObject::class, $build);
        (new RpcMetadataCache($pool, 'b1'))->get('k', \ArrayObject::class, $build);

        $this->assertInstanceOf(\Closure::class, $first['closure']);
        $this->assertSame(2, $builds);
        $pool->clear();
    }
}
