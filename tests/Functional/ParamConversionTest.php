<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;
use Ufo\JsonRpcBundle\Tests\Fixtures\ConvertKernel;
use Ufo\JsonRpcBundle\Tests\Fixtures\TestKernel;

/**
 * The full path of a request on the core route: the service map built by the compiler pass, parameter
 * conversion by IParamConvertor without #[RPC\Param], a DTO result. Several calls run in one kernel,
 * as in a batch or a long-running worker.
 */
class ParamConversionTest extends KernelTestCase
{
    protected static function getKernelClass(): string { return ConvertKernel::class; }

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        TestKernel::$rpcConfig = [];
        self::bootKernel();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    private function call(string $method, array $params): array
    {
        $content = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
        $response = self::$kernel->handle(Request::create('http://localhost/rpc', 'POST', content: $content));
        return json_decode($response->getContent(), true);
    }

    public function testEnumParameterIsConverted(): void
    {
        self::assertSame(['id' => 5, 'name' => 'n5', 'color' => 'blue'], $this->call('catalog.item', ['id' => 5, 'color' => 'blue'])['result'] ?? null);
        self::assertSame(['id' => 5, 'name' => 'n5', 'color' => 'blue'], $this->call('catalog.item', [5, 'blue'])['result'] ?? null);
    }

    public function testEnumDefaultIsTheEnumCase(): void
    {
        self::assertSame(['id' => 5, 'name' => 'n5', 'color' => 'red'], $this->call('catalog.item', ['id' => 5])['result'] ?? null);
        self::assertSame(['id' => 5, 'name' => 'n5', 'color' => 'red'], $this->call('catalog.item', [5])['result'] ?? null);
    }

    public function testServiceMapKeepsTheEnumDefaultAndDescribesTheConvertedType(): void
    {
        $map = self::getContainer()->get(ServiceMap::class);

        self::assertSame('red', $map->getService('catalog.item')->getParams()['color']->getDefault());
        self::assertSame(
            ['oneOf' => [['type' => 'string'], ['type' => 'null']]],
            $map->getService('catalog.day')->getParams()['date']->getType()
        );
    }

    public function testInvalidEnumValueIsAnError(): void
    {
        $response = $this->call('catalog.item', ['id' => 5, 'color' => 'green']);

        self::assertArrayNotHasKey('result', $response);
        self::assertArrayHasKey('error', $response);
    }

    public function testDateTimeParameterIsConverted(): void
    {
        self::assertSame('2026-10-19', $this->call('catalog.day', ['date' => '2026-10-19'])['result'] ?? null);
    }

    public function testResponsesInTheSameProcessDoNotShareSerializationGroups(): void
    {
        $this->call('catalog.item', ['id' => 5]);
        $error = $this->call('catalog.item', ['id' => 5, 'color' => 'green']);
        self::assertArrayNotHasKey('result', $error, 'no "result": null left over from the success response');
        self::assertArrayHasKey('error', $error);

        // Neither an empty result nor "error": null left over from the error response.
        self::assertEquals(
            ['jsonrpc' => '2.0', 'id' => 1, 'result' => ['id' => 7, 'name' => 'n7', 'color' => 'red']],
            $this->call('catalog.item', ['id' => 7])
        );
    }
}
