<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\DependencyInjection\Async;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\DependencyInjection\Async\AsyncTransportName;

class AsyncTransportNameTest extends TestCase
{
    public static function names(): iterable
    {
        yield 'suffix' => ['rpc_async', 'rpc_async.internal_dev.v2'];
        yield 'both placeholders' => ['rpc.{version}.{group}', 'rpc.v2.internal_dev'];
        yield 'group only' => ['rpc-{group}', 'rpc-internal_dev'];
        yield 'version only' => ['rpc-{version}', 'rpc-v2'];
    }

    #[DataProvider('names')]
    public function testExpand(string $name, string $expected): void
    {
        $this->assertSame($expected, AsyncTransportName::expand($name, 'internal_dev', 'v2'));
    }
}
