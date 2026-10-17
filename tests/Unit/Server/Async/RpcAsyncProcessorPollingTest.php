<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\Async;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;
use Ufo\JsonRpcBundle\Server\Async\RpcCallbackProcessor;
use Ufo\JsonRpcBundle\Server\RpcServer;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\Transformer\Transformer;

class RpcAsyncProcessorPollingTest extends TestCase
{
    public function testWaitingForProcessesDoesNotSpinTheCpu(): void
    {
        $processor = new RpcAsyncProcessor(
            $this->createMock(RpcServer::class),
            Transformer::getDefault(),
            $this->createMock(IRpcSecurity::class),
            $this->createMock(RpcCallbackProcessor::class),
            new \Ufo\JsonRpcBundle\Server\ApiGroupContext(),
            new \Ufo\JsonRpcBundle\ConfigService\RpcMainConfig([], 'test'),
        );

        $queue = &$processor->getProcesses();
        $counter = [];
        $requests = [];
        foreach (['a', 'b'] as $id) {
            $process = new Process([PHP_BINARY, '-r', 'usleep(500000); echo "'.$id.'";']);
            $process->start();
            $queue[$id] = $process;
            $counter[$id] = 0;
            $requests[$id] = new RpcRequest($id, 'ping');
        }
        (new \ReflectionProperty($processor, 'counter'))->setValue($processor, $counter);
        (new \ReflectionProperty($processor, 'requestObjects'))->setValue($processor, $requests);

        $cpuBefore = $this->cpuTime();
        $wallBefore = microtime(true);
        $results = $processor->process();
        $wall = microtime(true) - $wallBefore;
        $cpu = $this->cpuTime() - $cpuBefore;

        $this->assertEqualsCanonicalizing(['a' => 'a', 'b' => 'b'], $results);
        $this->assertSame([], $queue);
        $this->assertGreaterThan(0.3, $wall);
        // A busy-loop burns roughly the whole wait time; polling with a pause should use a small fraction of it.
        $this->assertLessThan($wall * 0.3, $cpu, sprintf('Waiting used %.3fs CPU over %.3fs wall time', $cpu, $wall));
    }

    private function cpuTime(): float
    {
        $usage = getrusage();
        return $usage['ru_utime.tv_sec'] + $usage['ru_utime.tv_usec'] / 1e6
            + $usage['ru_stime.tv_sec'] + $usage['ru_stime.tv_usec'] / 1e6;
    }
}
