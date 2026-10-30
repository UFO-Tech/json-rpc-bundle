<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\Tests\Fixtures\EnumDocKernel;
use Ufo\JsonRpcBundle\Tests\Fixtures\TestKernel;

/** Enum у документі OpenRPC — завжди $ref на компонент, і в параметрі, і в результаті. */
class EnumRefsInDocTest extends KernelTestCase
{
    private const string COLOR = '#/components/schemas/Color';
    private const string LEVEL = '#/components/schemas/Level';

    /** @var array<string, mixed> */
    private array $doc;

    protected static function getKernelClass(): string { return EnumDocKernel::class; }

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        TestKernel::$rpcConfig = [];
        self::bootKernel();
        $response = self::$kernel->handle(Request::create('http://localhost/rpc', 'GET'));
        $this->doc = json_decode($response->getContent(), true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testEnumResultIsRef(): void
    {
        self::assertSame(['$ref' => self::COLOR], $this->method('enums.one')['result']['schema']);
        self::assertSame(['$ref' => self::COLOR], $this->param('enums.one', 'color'));
    }

    public function testEnumListResultIsRef(): void
    {
        self::assertSame(['type' => 'array', 'items' => ['$ref' => self::COLOR]], $this->method('enums.list')['result']['schema']);
    }

    public function testNullableEnumResultIsRef(): void
    {
        self::assertSame(['oneOf' => [['$ref' => self::LEVEL], ['type' => 'null']]], $this->method('enums.maybe')['result']['schema']);
    }

    public function testEveryEnumOfUnionParamIsRef(): void
    {
        self::assertSame(['oneOf' => [['$ref' => self::COLOR], ['$ref' => self::LEVEL]]], $this->param('enums.union', 'value'));
    }

    public function testEnumMapParamIsRef(): void
    {
        self::assertSame(['type' => 'object', 'additionalProperties' => ['$ref' => self::COLOR]], $this->param('enums.map', 'map'));
    }

    public function testComponentsCarryEnumsWithoutServerClassNames(): void
    {
        $schemas = $this->doc['components']['schemas'];

        self::assertSame(['red', 'blue'], $schemas['Color']['enum'] ?? null);
        self::assertSame([0, 1], $schemas['Level']['enum'] ?? null);
        self::assertStringNotContainsString('classFQCN', json_encode($this->doc));
    }

    /** @return array<string, mixed> */
    private function method(string $name): array
    {
        foreach ($this->doc['methods'] as $method) {
            if ($method['name'] === $name) {
                return $method;
            }
        }
        self::fail('Method not in document: ' . $name);
    }

    /** @return array<string, mixed> */
    private function param(string $method, string $param): array
    {
        foreach ($this->method($method)['params'] as $p) {
            if ($p['name'] === $param) {
                return array_diff_key($p['schema'], ['default' => true]);
            }
        }
        self::fail('Param not in document: ' . $method . '.' . $param);
    }
}
