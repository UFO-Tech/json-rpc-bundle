<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\ParamConvertors;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\ParamConvertors\BackedEnumConvertor;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\JsonRpcBundle\Tests\Unit\MockBackedEnum;
use Ufo\JsonRpcBundle\Tests\Unit\MockBackedEnumWithAlias;
use Ufo\JsonRpcBundle\Tests\Unit\MockIntBackedEnum;
use Ufo\JsonRpcBundle\Tests\Unit\MockZeroIntBackedEnum;
use Ufo\JsonRpcBundle\Tests\Unit\MockZeroStringBackedEnum;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcObject\RPC\Param;

class BackedEnumConvertorTest extends TestCase
{
    protected BackedEnumConvertor $convertor;

    #[\Override]
    public function setUp(): void
    {
        $this->convertor = new BackedEnumConvertor();
    }

    public function testConvertsValidValueToObject(): void
    {
        $mockEnum = MockBackedEnum::A;
        $context = ['classFQCN' => MockBackedEnum::class];
        $result = $this->convertor->toObject('a', $context);
        $this->assertSame($mockEnum, $result);
    }

    public function testThrowsExceptionForInvalidValueToObject(): void
    {
        $this->expectException(RpcBadParamException::class);
        $this->expectExceptionMessage('Invalid value "invalidValue", expected one of: a, b');
        $mockEnumClass = MockBackedEnum::class;
        $context = ['classFQCN' => $mockEnumClass];
        $this->convertor->toObject('invalidValue', $context);
    }

    public function testValueOfAnotherTypeIsABadParamNotATypeError(): void
    {
        $this->expectException(RpcBadParamException::class);
        $this->expectExceptionMessage('Invalid value "one", expected one of: 1, 2');
        $this->convertor->toObject('one', ['classFQCN' => MockIntBackedEnum::class]);
    }

    public function testThrowsExceptionForMissingClassFQCNInContext(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('classFQCN is required in context');
        $this->convertor->toObject('value', []);
    }

    public function testThrowsExceptionForInvalidClassFQCN(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("stdClass is not a BackedEnum");
        $context = ['classFQCN' => \stdClass::class];
        $this->convertor->toObject('b', $context);
    }

    /**
     * It should apply a callback during conversion to object.
     */
    public function testAppliesCallbackDuringToObjectConversion(): void
    {
        $mockEnum = MockBackedEnum::tryFrom('b');

        $context = ['classFQCN' => get_class($mockEnum)];
        $callback = fn($value, $enum) => $enum;
        $result = $this->convertor->toObject('b', $context, $callback);
        $this->assertSame($mockEnum, $result);
    }

    /**
     * It should return true for a class implementing BackedEnum.
     */
    public function testReturnsTrueForSupportedClass(): void
    {
        $result = $this->convertor->supported(MockBackedEnum::class);
        $this->assertTrue($result);
    }

    /**
     * It should return false for a class not implementing BackedEnum.
     */
    public function testReturnsFalseForUnsupportedClass(): void
    {
        $result = $this->convertor->supported(\stdClass::class);
        $this->assertFalse($result);
    }

    public function testToScalarConvertsBackedEnumValue(): void
    {
        $this->assertSame('a', $this->convertor->toScalar(MockBackedEnum::A));
    }

    public function testToScalarThrowsForNonEnumObject(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->convertor->toScalar(new \stdClass());
    }

    public function testGetParamAttrReturnsConvertorContextAndBackingType(): void
    {
        $param = $this->convertor->getParamAttr(MockBackedEnum::class);

        $this->assertInstanceOf(Param::class, $param);
        $this->assertSame('string', $param->getType());
        $this->assertSame(BackedEnumConvertor::class, $param->context[Param::C_CONVERTOR] ?? null);
    }

    public function testToObjectUsesTryFromValueFallbackWhenTryFromFails(): void
    {
        $context = ['classFQCN' => MockBackedEnumWithAlias::class];
        $result = $this->convertor->toObject('alias', $context);

        $this->assertSame(MockBackedEnumWithAlias::A, $result);
    }

    public function testIntZeroConvertsToTheZeroCase(): void
    {
        $context = ['classFQCN' => MockZeroIntBackedEnum::class];

        $this->assertSame(MockZeroIntBackedEnum::ZERO, $this->convertor->toObject(0, $context));
        $this->assertSame(MockZeroIntBackedEnum::ZERO, $this->convertor->toObject('0', $context));
    }

    public function testStringZeroConvertsToTheZeroCase(): void
    {
        $context = ['classFQCN' => MockZeroStringBackedEnum::class];

        $this->assertSame(MockZeroStringBackedEnum::ZERO, $this->convertor->toObject('0', $context));
        $this->assertSame(MockZeroStringBackedEnum::ZERO, $this->convertor->toObject(0, $context));
    }

    public function testToScalarKeepsZero(): void
    {
        $this->assertSame(0, $this->convertor->toScalar(MockZeroIntBackedEnum::ZERO));
        $this->assertSame('0', $this->convertor->toScalar(MockZeroStringBackedEnum::ZERO));
    }

    public function testInvalidValueMessageListsZero(): void
    {
        $this->expectException(RpcBadParamException::class);
        $this->expectExceptionMessage('Invalid value "5", expected one of: 0, 1');
        $this->convertor->toObject(5, ['classFQCN' => MockZeroIntBackedEnum::class]);
    }

    #[DataProvider('notAnIntegerProvider')]
    public function testNotAnIntegerIsNotTakenForTheZeroCase(mixed $value): void
    {
        $this->expectException(RpcBadParamException::class);
        $this->convertor->toObject($value, ['classFQCN' => MockZeroIntBackedEnum::class]);
    }

    public static function notAnIntegerProvider(): iterable
    {
        yield 'float zero' => [0.0];
        yield 'numeric float string' => ['0.0'];
        yield 'null' => [null];
    }

    public function testNullForNullableParamIsNull(): void
    {
        $this->assertNull($this->convertor->toObject(null, [
            'classFQCN' => MockBackedEnum::class,
            'paramDefinition' => new ParamDefinition('e', [], ['null', MockBackedEnum::class]),
        ]));
    }

    public function testNullForRequiredParamIsABadParam(): void
    {
        $this->expectException(RpcBadParamException::class);
        $this->convertor->toObject(null, [
            'classFQCN' => MockBackedEnum::class,
            'paramDefinition' => new ParamDefinition('e', [], MockBackedEnum::class),
        ]);
    }

    public function testInvalidValueForNullableParamIsABadParam(): void
    {
        $this->expectException(RpcBadParamException::class);
        $this->convertor->toObject('invalid', [
            'classFQCN' => MockBackedEnum::class,
            'paramDefinition' => new ParamDefinition('e', [], ['null', MockBackedEnum::class]),
        ]);
    }

    #[DataProvider('nullableParamAttrProvider')]
    public function testGetNullableParamAttrAddsNullOnlyForNullableParam(string $enum, array|string $realType, array|string $expected): void
    {
        $param = $this->convertor->getNullableParamAttr($enum, new ParamDefinition('e', [], $realType));

        $this->assertSame($expected, $param->getType());
        $this->assertSame(BackedEnumConvertor::class, $param->context[Param::C_CONVERTOR] ?? null);
    }

    public static function nullableParamAttrProvider(): iterable
    {
        yield 'nullable string enum' => [MockBackedEnum::class, ['null', MockBackedEnum::class], ['string', 'null']];
        yield 'required string enum' => [MockBackedEnum::class, MockBackedEnum::class, 'string'];
        yield 'nullable int enum' => [MockIntBackedEnum::class, '?' . MockIntBackedEnum::class, ['int', 'null']];
        yield 'required int enum' => [MockIntBackedEnum::class, MockIntBackedEnum::class, 'int'];
    }

}
