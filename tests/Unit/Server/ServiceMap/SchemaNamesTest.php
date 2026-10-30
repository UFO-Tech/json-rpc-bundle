<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\ServiceMap;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\Server\ServiceMap\SchemaNames;
use Ufo\RpcError\RpcInternalException;

class SchemaNamesTest extends TestCase
{
    public function testUniqueShortNamesPass(): void
    {
        SchemaNames::assertUnique(['App\Enum\Color', 'App\Dto\PointDto', '\App\Enum\Color']);

        $this->addToAssertionCount(1);
    }

    public function testEnumNamesakesAreAnError(): void
    {
        $this->expectException(RpcInternalException::class);
        $this->expectExceptionMessage('"Color" (App\Enum\Color, App\Enum\Paint\Color)');

        SchemaNames::assertUnique(['App\Enum\Paint\Color', 'App\Enum\Color']);
    }

    public function testEnumAndDtoNamesakesAreAnError(): void
    {
        $this->expectException(RpcInternalException::class);
        $this->expectExceptionMessage('"Size" (App\Dto\Size, App\Enum\Size)');

        SchemaNames::assertUnique(['App\Enum\Size', 'App\Dto\Size']);
    }

    public function testEveryClashIsListed(): void
    {
        $this->expectException(RpcInternalException::class);
        $this->expectExceptionMessage('"Color" (A\Color, B\Color); "Size" (A\Size, B\Size)');

        SchemaNames::assertUnique(['B\Size', 'A\Color', 'A\Size', 'B\Color', 'A\PointDto']);
    }

    public function testShortName(): void
    {
        self::assertSame('Color', SchemaNames::shortName('App\Enum\Color'));
        self::assertSame('Color', SchemaNames::shortName('Color'));
    }
}
