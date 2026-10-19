<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\ParamConvertors;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Ufo\JsonRpcBundle\ParamConvertors\EnumConvertor;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\JsonRpcBundle\Tests\Unit\MockBackedEnum;
use Ufo\JsonRpcBundle\Tests\Unit\MockPureEnum;
use Ufo\JsonRpcBundle\Tests\Unit\MockPureEnumWithAlias;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcObject\RPC\Param;

class EnumConvertorTest extends TestCase
{
    protected EnumConvertor $convertor;

    #[\Override]
    public function setUp(): void
    {
        $this->convertor = new EnumConvertor();
    }

    public function testConvertsCaseNameToObject(): void
    {
        $context = ['classFQCN' => MockPureEnum::class];

        $this->assertSame(MockPureEnum::Draft, $this->convertor->toObject('Draft', $context));
        $this->assertSame(MockPureEnum::Published, $this->convertor->toObject('Published', $context));
    }

    public function testCaseNameIsCaseInsensitive(): void
    {
        $context = ['classFQCN' => MockPureEnum::class];

        $this->assertSame(MockPureEnum::Draft, $this->convertor->toObject('draft', $context));
        $this->assertSame(MockPureEnum::Published, $this->convertor->toObject('PUBLISHED', $context));
    }

    public function testFallsBackToTryFromValue(): void
    {
        $this->assertSame(
            MockPureEnumWithAlias::Published,
            $this->convertor->toObject('pub', ['classFQCN' => MockPureEnumWithAlias::class])
        );
    }

    #[DataProvider('invalidValueProvider')]
    public function testInvalidValueIsABadParam(int|string|float|null $value, string $printed): void
    {
        $this->expectException(RpcBadParamException::class);
        $this->expectExceptionMessage(sprintf('Invalid value "%s", expected one of: Draft, Published', $printed));
        $this->convertor->toObject($value, ['classFQCN' => MockPureEnum::class]);
    }

    public static function invalidValueProvider(): iterable
    {
        yield 'unknown name' => ['Archived', 'Archived'];
        yield 'empty string' => ['', ''];
        yield 'int' => [1, '1'];
        yield 'float zero' => [0.0, '0'];
        yield 'null' => [null, ''];
    }

    public function testInvalidAliasIsABadParam(): void
    {
        $this->expectException(RpcBadParamException::class);
        $this->convertor->toObject('unknown', ['classFQCN' => MockPureEnumWithAlias::class]);
    }

    public function testNullForOptionalParamWithNullDefaultIsNull(): void
    {
        $paramDefinition = (new ParamDefinition('status', ['string'], '?'.MockPureEnum::class))->setDefault(null);

        $this->assertNull($this->convertor->toObject(null, [
            'classFQCN' => MockPureEnum::class,
            'paramDefinition' => $paramDefinition,
        ]));
    }

    public function testInvalidValueForOptionalParamWithNullDefaultIsABadParam(): void
    {
        $paramDefinition = (new ParamDefinition('status', ['string'], '?'.MockPureEnum::class))->setDefault(null);

        $this->expectException(RpcBadParamException::class);
        $this->convertor->toObject('Archived', [
            'classFQCN' => MockPureEnum::class,
            'paramDefinition' => $paramDefinition,
        ]);
    }

    public function testNullForRequiredNullableParamIsNull(): void
    {
        $this->assertNull($this->convertor->toObject(null, [
            'classFQCN' => MockPureEnum::class,
            'paramDefinition' => new ParamDefinition('status', ['string'], [MockPureEnum::class, 'null']),
        ]));
    }

    public function testInvalidValueForOptionalParamWithCaseDefaultIsABadParam(): void
    {
        $paramDefinition = (new ParamDefinition('status', ['string'], MockPureEnum::class))->setDefault('Draft');

        $this->expectException(RpcBadParamException::class);
        $this->convertor->toObject('Archived', [
            'classFQCN' => MockPureEnum::class,
            'paramDefinition' => $paramDefinition,
        ]);
    }

    public function testAppliesCallbackDuringToObjectConversion(): void
    {
        $received = null;
        $callback = function (string $value, MockPureEnum $enum) use (&$received): MockPureEnum {
            $received = [$value, $enum];
            return MockPureEnum::Published;
        };

        $result = $this->convertor->toObject('draft', ['classFQCN' => MockPureEnum::class], $callback);

        $this->assertSame(MockPureEnum::Published, $result);
        $this->assertSame(['draft', MockPureEnum::Draft], $received);
    }

    public function testThrowsExceptionForMissingClassFQCNInContext(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('classFQCN is required in context');
        $this->convertor->toObject('Draft');
    }

    public function testThrowsExceptionForNonEnumClass(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('stdClass is not a UnitEnum or BackedEnum');
        $this->convertor->toObject('Draft', ['classFQCN' => stdClass::class]);
    }

    public function testToScalarReturnsCaseName(): void
    {
        $this->assertSame('Draft', $this->convertor->toScalar(MockPureEnum::Draft));
    }

    public function testToScalarAppliesCallback(): void
    {
        $this->assertSame(
            'published',
            $this->convertor->toScalar(MockPureEnum::Published, callback: fn (string $value): string => strtolower($value))
        );
    }

    public function testToScalarThrowsForNonEnumObject(): void
    {
        $this->expectException(RuntimeException::class);
        $this->convertor->toScalar(new stdClass());
    }

    public function testSupportsOnlyPureEnums(): void
    {
        $this->assertTrue($this->convertor->supported(MockPureEnum::class));
        $this->assertFalse($this->convertor->supported(MockBackedEnum::class));
        $this->assertFalse($this->convertor->supported(stdClass::class));
    }

    public function testGetParamAttrIsAStringWithConvertorContext(): void
    {
        $param = $this->convertor->getParamAttr(MockPureEnum::class);

        $this->assertSame('string', $param->getType());
        $this->assertSame(EnumConvertor::class, $param->context[Param::C_CONVERTOR] ?? null);
    }

    public function testGetNullableParamAttrAddsNullOnlyForNullableParam(): void
    {
        $nullable = $this->convertor->getNullableParamAttr(MockPureEnum::class, new ParamDefinition('e', [], ['null', MockPureEnum::class]));
        $required = $this->convertor->getNullableParamAttr(MockPureEnum::class, new ParamDefinition('e', [], MockPureEnum::class));

        $this->assertSame(['string', 'null'], $nullable->getType());
        $this->assertSame('string', $required->getType());
        $this->assertSame(EnumConvertor::class, $nullable->context[Param::C_CONVERTOR] ?? null);
    }
}
