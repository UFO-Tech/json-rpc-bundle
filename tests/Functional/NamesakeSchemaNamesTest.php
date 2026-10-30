<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\Tests\Fixtures\EnumDocKernel;
use Ufo\JsonRpcBundle\Tests\Fixtures\NamesakeDocKernel;
use Ufo\JsonRpcBundle\Tests\Fixtures\TestKernel;
use Ufo\RpcError\RpcInternalException;

/** Тезки серед DTO та enum API ламають збірку контейнера; у документі немає повних імен класів. */
class NamesakeSchemaNamesTest extends KernelTestCase
{
    private static string $kernel_ = EnumDocKernel::class;

    protected static function getKernelClass(): string { return self::$kernel_; }

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        TestKernel::$rpcConfig = [];
    }

    protected function tearDown(): void
    {
        self::$kernel_ = EnumDocKernel::class;
        parent::tearDown();
        restore_exception_handler();
    }

    public function testNamesakesBreakContainerBuild(): void
    {
        self::$kernel_ = NamesakeDocKernel::class;

        try {
            self::bootKernel();
            self::fail('Container with namesake API classes must not compile');
        } catch (RpcInternalException $e) {
            self::assertStringContainsString('"Color" (Ufo\JsonRpcBundle\Tests\Fixtures\Dto\Color, Ufo\JsonRpcBundle\Tests\Fixtures\Namesake\Color)', $e->getMessage());
            self::assertStringContainsString('"ItemDto"', $e->getMessage());
            self::assertStringContainsString('"Level"', $e->getMessage());
        }
    }

    public function testSchemasHaveNoServerClassNames(): void
    {
        self::bootKernel();
        $response = self::$kernel->handle(Request::create('http://localhost/rpc', 'GET'));
        $doc = json_decode($response->getContent(), true);

        foreach ($doc['methods'] as $i => $method) {
            self::assertNotEmpty($method['tags'][0]['name'] ?? null, $method['name']);
            unset($doc['methods'][$i]['tags']);
        }
        $json = json_encode($doc);

        self::assertStringNotContainsString('Ufo\\\\JsonRpcBundle\\\\Tests', $json);
        self::assertStringNotContainsString('classFQCN', $json);
    }
}
