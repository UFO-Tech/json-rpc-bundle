<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\ApiMethod;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\ApiMethod\PingProcedure;

class PingProcedureTest extends TestCase
{

    public function testPing(): void
    {
        $pingProcedure = new PingProcedure();
        $result = $pingProcedure->ping();
        $this->assertEquals("PONG", $result, "The ping method should return 'PONG'.");
    }

}
