<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\ConfigService;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\ConfigService\RpcBatchConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;

class RpcBatchConfigTest extends TestCase
{
    public function testDefaultsWhenBatchIsNotConfigured(): void
    {
        $config = (new RpcMainConfig([], 'test'))->batchConfig;

        $this->assertTrue($config->enabled);
        $this->assertTrue($config->process);
        $this->assertSame(RpcBatchConfig::DEFAULT_PROCESS_COUNT, $config->processCount);
    }

    public function testConfiguredValues(): void
    {
        $config = (new RpcMainConfig([
            RpcBatchConfig::NAME => [
                RpcBatchConfig::ENABLED => false,
                RpcBatchConfig::PROCESS => false,
                RpcBatchConfig::PROCESS_COUNT => 7,
            ],
        ], 'test'))->batchConfig;

        $this->assertFalse($config->enabled);
        $this->assertFalse($config->process);
        $this->assertSame(7, $config->processCount);
    }
}
