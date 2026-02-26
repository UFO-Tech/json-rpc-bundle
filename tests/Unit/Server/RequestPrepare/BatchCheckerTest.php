<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\RequestPrepare;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\Server\RequestPrepare\BatchChecker;

class BatchCheckerTest extends TestCase
{
    public function testDetectsBatchRequestByFirstNonWhitespaceCharacter(): void
    {
        $checker = new class {
            use BatchChecker;

            public function detect(string $content): bool
            {
                return $this->checkBatchRequest($content);
            }
        };

        $this->assertTrue($checker->detect("   \n\t[ {\"jsonrpc\":\"2.0\"} ]"));
        $this->assertFalse($checker->detect("  {\"jsonrpc\":\"2.0\"}"));
    }
}

