<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\Async;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\ConfigService\RpcBatchConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;
use Ufo\JsonRpcBundle\Server\Async\RpcCallbackProcessor;
use Ufo\JsonRpcBundle\Server\RpcServer;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\Transformer\Transformer;

class RpcAsyncProcessorLimitTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        // Fake bin/console: logs start/end time of every child, sleeps a bit, prints its stdin back.
        $this->projectDir = sys_get_temp_dir().'/ufo-rpc-limit-'.bin2hex(random_bytes(8));
        mkdir($this->projectDir.'/bin', recursive: true);
        file_put_contents($this->projectDir.'/bin/console', '<?php
            $log = __DIR__."/times.log";
            $start = microtime(true);
            usleep(300000);
            file_put_contents($log, $start." ".microtime(true)."\n", FILE_APPEND | LOCK_EX);
            echo stream_get_contents(STDIN);');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->projectDir.'/bin/*'));
        rmdir($this->projectDir.'/bin');
        rmdir($this->projectDir);
    }

    private function processor(int $processCount): RpcAsyncProcessor
    {
        return new RpcAsyncProcessor(
            $this->createMock(RpcServer::class),
            Transformer::getDefault(),
            $this->createMock(IRpcSecurity::class),
            $this->createMock(RpcCallbackProcessor::class),
            new \Ufo\JsonRpcBundle\Server\ApiGroupContext(),
            new RpcMainConfig([RpcBatchConfig::NAME => [RpcBatchConfig::PROCESS_COUNT => $processCount]], 'test'),
            $this->projectDir,
        );
    }

    /**
     * @return array{0: array<string|int, string>, 1: int}
     */
    private function runBatch(RpcAsyncProcessor $processor, int $items): array
    {
        for ($i = 1; $i <= $items; $i++) {
            $processor->createProcesses(new RpcRequest($i, 'ping'));
        }
        $results = $processor->process();

        $intervals = array_map(
            fn(string $line) => array_map('floatval', explode(' ', $line)),
            file($this->projectDir.'/bin/times.log', FILE_IGNORE_NEW_LINES)
        );
        $maxParallel = 0;
        foreach ($intervals as [$start]) {
            $running = count(array_filter($intervals, fn($other) => $other[0] <= $start && $start < $other[1]));
            $maxParallel = max($maxParallel, $running);
        }

        return [$results, $maxParallel];
    }

    public function testNoMoreThanProcessCountChildrenRunAtOnce(): void
    {
        [$results, $maxParallel] = $this->runBatch($this->processor(2), 5);

        $this->assertCount(5, $results);
        $ids = array_keys($results);
        sort($ids);
        $this->assertSame([1, 2, 3, 4, 5], $ids);
        $this->assertLessThanOrEqual(2, $maxParallel);
        $this->assertSame(2, $maxParallel);
    }

    public function testItemsUpToProcessCountRunInParallel(): void
    {
        [$results, $maxParallel] = $this->runBatch($this->processor(RpcBatchConfig::MAX_PROCESS_COUNT), 4);

        $this->assertCount(4, $results);
        $this->assertSame(4, $maxParallel);
    }

}
