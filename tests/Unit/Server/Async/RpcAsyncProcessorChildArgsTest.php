<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\Async;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\CliCommand\UfoRpcProcessCommand;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;
use Ufo\JsonRpcBundle\Server\Async\RpcCallbackProcessor;
use Ufo\JsonRpcBundle\Server\RpcServer;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\Transformer\Transformer;

class RpcAsyncProcessorChildArgsTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        // Fake bin/console that reports what the child process actually received.
        $this->projectDir = sys_get_temp_dir().'/ufo-rpc-child-'.bin2hex(random_bytes(8));
        mkdir($this->projectDir.'/bin', recursive: true);
        file_put_contents($this->projectDir.'/bin/console', '<?php echo json_encode(['
            .'"argv" => array_slice($argv, 1),'
            .'"stdin" => stream_get_contents(STDIN),'
            .'"token" => getenv("'.UfoRpcProcessCommand::TOKEN_ENV.'"),'
            .']);');
    }

    protected function tearDown(): void
    {
        unlink($this->projectDir.'/bin/console');
        rmdir($this->projectDir.'/bin');
        rmdir($this->projectDir);
    }

    public function testTokenAndRequestAreNotPassedInArgv(): void
    {
        $processor = new RpcAsyncProcessor(
            $this->createMock(RpcServer::class),
            Transformer::getDefault(),
            $this->createMock(IRpcSecurity::class),
            $this->createMock(RpcCallbackProcessor::class),
            new \Ufo\JsonRpcBundle\Server\ApiGroupContext(),
            new \Ufo\JsonRpcBundle\ConfigService\RpcMainConfig([], 'test'),
            $this->projectDir,
        );
        $request = RpcRequest::fromJson('{"jsonrpc":"2.0","id":1,"method":"ping","params":{"secret":"s3cr3t"}}');

        $process = $processor->createProcesses($request, 'ufo-rpc-token', 'super-secret-token');
        $process->wait();
        $child = json_decode($process->getOutput(), true);

        $this->assertSame(UfoRpcProcessCommand::COMMAND_NAME, $child['argv'][0]);
        $this->assertSame(UfoRpcProcessCommand::JSON_FROM_STDIN, $child['argv'][1]);
        $argv = implode(' ', $child['argv']);
        $this->assertStringNotContainsString('super-secret-token', $argv);
        $this->assertStringNotContainsString('s3cr3t', $argv);

        $this->assertSame('super-secret-token', $child['token']);
        $this->assertSame((string)$request->getRawJson(), $child['stdin']);
    }
}
