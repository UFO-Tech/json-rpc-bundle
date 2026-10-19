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
        self::assertSame(-32602, $response['error']['code'] ?? null);
        self::assertSame('Invalid Data for call method: catalog.item', $response['error']['message'] ?? null);
        self::assertSame(['color' => ['Invalid value "green", expected one of: red, blue']], $response['error']['data'] ?? null);
    }

    public function testMissingRequiredParamIsAListOfMessages(): void
    {
        $response = $this->call('catalog.item', ['color' => 'blue']);

        self::assertSame(-32602, $response['error']['code'] ?? null);
        self::assertSame(['id' => ['Required parameter "id" not passed']], $response['error']['data'] ?? null);
    }

    public function testArgumentTypeErrorIsAListOfMessages(): void
    {
        $response = $this->call('catalog.item', ['id' => 'abc']);

        self::assertSame(-32602, $response['error']['code'] ?? null);
        self::assertSame(['id' => ['Parameter "id" must be of type int, string given']], $response['error']['data'] ?? null);
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

    public function testIntEnumZeroIsConverted(): void
    {
        self::assertSame(['id' => 1, 'level' => 0], $this->call('catalog.level', ['id' => 1, 'level' => 0])['result'] ?? null);
        self::assertSame(['id' => 1, 'level' => 0], $this->call('catalog.level', ['id' => 1, 'level' => '0'])['result'] ?? null);
        self::assertSame(['id' => 1, 'level' => 0], $this->call('catalog.level', [1, 0])['result'] ?? null);
    }

    public function testIntEnumZeroIsTheDefault(): void
    {
        self::assertSame(['id' => 1, 'level' => 0], $this->call('catalog.level', ['id' => 1])['result'] ?? null);
        self::assertSame(['id' => 1, 'level' => 0], $this->call('catalog.level', [1])['result'] ?? null);
        self::assertSame(
            0,
            self::getContainer()->get(ServiceMap::class)->getService('catalog.level')->getParams()['level']->getDefault()
        );
    }

    public function testInvalidIntEnumValueListsZero(): void
    {
        $response = $this->call('catalog.level', ['id' => 1, 'level' => 5]);

        self::assertSame(-32602, $response['error']['code'] ?? null);
        self::assertSame(['level' => ['Invalid value "5", expected one of: 0, 1']], $response['error']['data'] ?? null);
    }

    public function testFalseIsNotTakenForTheIntEnumZero(): void
    {
        $response = $this->call('catalog.level', ['id' => 1, 'level' => false]);

        self::assertArrayNotHasKey('result', $response);
        self::assertSame(-32602, $response['error']['code'] ?? null);
        self::assertArrayHasKey('level', $response['error']['data'] ?? []);
    }

    public function testIntEnumZeroInDtoIsHydrated(): void
    {
        self::assertSame(0, $this->call('catalog.levelOf', ['dto' => ['id' => 1, 'level' => 0]])['result'] ?? null);
        self::assertSame(0, $this->call('catalog.levelOf', ['dto' => ['id' => 1, 'level' => '0']])['result'] ?? null);
    }

    public function testServiceMapDescribesTheIntEnumWithZero(): void
    {
        $type = self::getContainer()->get(ServiceMap::class)->getService('catalog.level')->getParams()['level']->getType();

        self::assertSame([0, 1], $type['enum'] ?? null);
        self::assertSame(['Zero' => 0, 'One' => 1], $type['x-ufo-enum']['values'] ?? null);
    }

    public function testNullableEnumWithNullDefaultIsConverted(): void
    {
        self::assertSame(['id' => 1, 'dir' => 'desc'], $this->call('catalog.sortNullable', ['id' => 1, 'dir' => 'desc'])['result'] ?? null);
        self::assertSame(['id' => 1, 'dir' => 'desc'], $this->call('catalog.sortNullable', [1, 'desc'])['result'] ?? null);
        self::assertSame(['id' => 1, 'dir' => null], $this->call('catalog.sortNullable', ['id' => 1, 'dir' => null])['result'] ?? null);
        self::assertSame(['id' => 1, 'dir' => null], $this->call('catalog.sortNullable', ['id' => 1])['result'] ?? null);
        self::assertSame(['id' => 1, 'dir' => null], $this->call('catalog.sortNullable', [1])['result'] ?? null);
    }

    public function testInvalidValueOfNullableEnumIsAnError(): void
    {
        $response = $this->call('catalog.sortNullable', ['id' => 1, 'dir' => 'up']);

        self::assertArrayNotHasKey('result', $response);
        self::assertSame(-32602, $response['error']['code'] ?? null);
        self::assertSame(['dir' => ['Invalid value "up", expected one of: asc, desc']], $response['error']['data'] ?? null);
    }

    public function testEnumWithCaseDefaultIsConverted(): void
    {
        self::assertSame(['id' => 1, 'dir' => 'desc'], $this->call('catalog.sortWithDefault', ['id' => 1, 'dir' => 'desc'])['result'] ?? null);
        self::assertSame(['id' => 1, 'dir' => 'desc'], $this->call('catalog.sortWithDefault', [1, 'desc'])['result'] ?? null);
        self::assertSame(['id' => 1, 'dir' => 'asc'], $this->call('catalog.sortWithDefault', ['id' => 1])['result'] ?? null);
        self::assertSame(['id' => 1, 'dir' => 'asc'], $this->call('catalog.sortWithDefault', [1])['result'] ?? null);

        $response = $this->call('catalog.sortWithDefault', ['id' => 1, 'dir' => 'up']);
        self::assertSame(['dir' => ['Invalid value "up", expected one of: asc, desc']], $response['error']['data'] ?? null);
    }

    public function testServiceMapDescribesNullableAndDefaultedEnums(): void
    {
        $map = self::getContainer()->get(ServiceMap::class);

        $nullable = $map->getService('catalog.sortNullable')->getParams()['dir'];
        self::assertTrue($nullable->isOptional());
        self::assertNull($nullable->getDefault());

        $defaulted = $map->getService('catalog.sortWithDefault')->getParams()['dir'];
        self::assertTrue($defaulted->isOptional());
        self::assertSame('asc', $defaulted->getDefault());
    }

    public function testNullableDateWithNullDefaultIsConverted(): void
    {
        self::assertSame(['id' => 1, 'date' => '2026-10-19'], $this->call('catalog.dayOrNull', ['id' => 1, 'date' => '2026-10-19'])['result'] ?? null);
        self::assertSame(['id' => 1, 'date' => null], $this->call('catalog.dayOrNull', ['id' => 1])['result'] ?? null);
    }
}
