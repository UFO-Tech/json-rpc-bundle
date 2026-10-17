<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\Async;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\Server\Async\RpcDetachedSlots;

class RpcDetachedSlotsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/ufo-rpc-slots-'.bin2hex(random_bytes(6));
    }

    public function testHeldSlotIsBusyForOthers(): void
    {
        $slots = new RpcDetachedSlots($this->dir);
        $this->assertSame(0, $slots->free(2));

        $holder = new RpcDetachedSlots($this->dir);   // another process in real life
        $holder->hold(0);

        $this->assertSame(1, $slots->free(2));
        $this->assertNull($slots->free(1));
    }

    public function testSlotIsFreeAgainWhenHolderIsGone(): void
    {
        $holder = new RpcDetachedSlots($this->dir);
        $holder->hold(0);
        unset($holder);   // the process exits: the OS releases the lock

        $this->assertSame(0, (new RpcDetachedSlots($this->dir))->free(1));
    }
}
