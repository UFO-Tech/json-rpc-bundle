<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\Async;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\CliCommand\UfoRpcProcessCommand;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcTokenHolder;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;
use Ufo\JsonRpcBundle\Server\Async\RpcCallbackProcessor;
use Ufo\JsonRpcBundle\Server\Async\RpcDetachedSlots;
use Ufo\JsonRpcBundle\Server\RpcServer;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\Transformer\Transformer;

class RpcAsyncProcessorDetachTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        // Fake bin/console: holds its slot, waits, then reports what it got.
        $this->projectDir = sys_get_temp_dir().'/ufo-rpc-detach-'.bin2hex(random_bytes(6));
        mkdir($this->projectDir.'/bin', recursive: true);
        file_put_contents($this->projectDir.'/bin/console', '<?php
            require "'.dirname(__DIR__, 4).'/vendor/autoload.php";
            parse_str(implode("&", array_map(fn($a) => ltrim($a, "-"), array_slice($argv, 3))), $opts);
            $slots = new Ufo\JsonRpcBundle\Server\Async\RpcDetachedSlots(__DIR__);
            $slots->hold((int)$opts["'.UfoRpcProcessCommand::DETACHED_SLOT.'"]);
            usleep((int)(getenv("FAKE_BOOT_US") ?: 0));   // kernel boot
            $claimed = $slots->claim($opts["'.UfoRpcProcessCommand::DETACHED_TICKET.'"]);
            if (!$claimed) { file_put_contents(__DIR__."/skipped", "1"); exit(0); }
            usleep(600000);
            file_put_contents(__DIR__."/done.json", json_encode([
                "argv" => array_slice($argv, 1),
                "request" => getenv("'.UfoRpcProcessCommand::REQUEST_ENV.'"),
                "token" => getenv("'.UfoRpcProcessCommand::TOKEN_ENV.'"),
                "stdin" => stream_get_contents(STDIN),
            ]));');
    }

    private function processor(float $handoverTimeout = 3.0): RpcAsyncProcessor
    {
        $tokenHolder = $this->createMock(IRpcTokenHolder::class);
        $tokenHolder->method('getToken')->willReturn('secret-token');
        $security = $this->createMock(IRpcSecurity::class);
        $security->method('getTokenHolder')->willReturn($tokenHolder);

        $args = [
            $this->createMock(RpcServer::class),
            Transformer::getDefault(),
            $security,
            $this->createMock(RpcCallbackProcessor::class),
            new ApiGroupContext(),
            new RpcMainConfig(['notification' => ['process' => true, 'process_count' => 1]], 'test'),
            $this->projectDir,
            new RpcDetachedSlots($this->projectDir.'/bin'),
        ];
        return $handoverTimeout === 3.0
            ? new RpcAsyncProcessor(...$args)
            : new class(...$args) extends RpcAsyncProcessor { const float DETACH_HANDOVER_TIMEOUT = 0.3; };
    }

    /**
     * Zombie children of this process with the given command name. Only the shell is a direct child of
     * the worker; the detached command is adopted by the init process (when the test itself runs as PID 1,
     * e.g. `docker run` without an init, it adopts it and never reaps it, so that case is not counted).
     */
    private function zombieChildren(string $comm): int
    {
        $zombies = 0;
        foreach (glob('/proc/[0-9]*/stat') ?: [] as $stat) {
            $fields = explode(' ', (string)@file_get_contents($stat));
            if (($fields[3] ?? null) == getmypid() && ($fields[2] ?? '') === 'Z' && ($fields[1] ?? '') === '('.$comm.')') $zombies++;
        }
        return $zombies;
    }

    public function testDetachedProcessRunsAfterReturnWithoutZombies(): void
    {
        $request = RpcRequest::fromJson('{"jsonrpc":"2.0","method":"bench.mark","params":{"id":42}}');
        $processor = $this->processor();

        $start = microtime(true);
        $this->assertTrue($processor->detach($request));
        $elapsed = microtime(true) - $start;

        // Returned long before the detached process is done (it sleeps 0.6 s).
        $this->assertLessThan(0.4, $elapsed);
        $this->assertFileDoesNotExist($this->projectDir.'/bin/done.json');
        usleep(150000);
        // Its slot is busy while it runs: with process_count 1 the next one is refused (runs inline).
        $this->assertFalse($processor->detach($request));

        for ($i = 0; $i < 30 && !is_file($this->projectDir.'/bin/done.json'); $i++) usleep(100000);
        $child = json_decode((string)file_get_contents($this->projectDir.'/bin/done.json'), true);

        $this->assertSame(UfoRpcProcessCommand::COMMAND_NAME, $child['argv'][0]);
        $this->assertSame(UfoRpcProcessCommand::JSON_FROM_ENV, $child['argv'][1]);
        $this->assertStringNotContainsString('secret-token', implode(' ', $child['argv']));
        $this->assertSame((string)$request->getRawJson(), $child['request']);
        $this->assertSame('secret-token', $child['token']);
        $this->assertSame(0, $this->zombieChildren('sh'), 'the shell is reaped, no zombie is left in the worker');

        usleep(100000);
        $this->assertSame(0, (new RpcDetachedSlots($this->projectDir.'/bin'))->free(1), 'slot is released when the process exits');
    }

    public function testWorkerTakesRequestBackWhenProcessDoesNotClaimItInTime(): void
    {
        // The process "boots" longer than the hand-over timeout (Symfony Process passes on env known to $_SERVER).
        putenv('FAKE_BOOT_US=800000');
        $_SERVER['FAKE_BOOT_US'] = '800000';
        try {
            $request = RpcRequest::fromJson('{"jsonrpc":"2.0","method":"bench.mark","params":{"id":1}}');
            $start = microtime(true);
            $detached = $this->processor(0.3)->detach($request);
            $elapsed = microtime(true) - $start;

            $this->assertFalse($detached, 'not taken over: the caller executes it');
            $this->assertLessThan(0.7, $elapsed);

            for ($i = 0; $i < 30 && !is_file($this->projectDir.'/bin/skipped'); $i++) usleep(100000);
            $this->assertFileExists($this->projectDir.'/bin/skipped', 'the late process does not execute it');
            $this->assertFileDoesNotExist($this->projectDir.'/bin/done.json');
        } finally {
            putenv('FAKE_BOOT_US');
            unset($_SERVER['FAKE_BOOT_US']);
        }
    }
}
