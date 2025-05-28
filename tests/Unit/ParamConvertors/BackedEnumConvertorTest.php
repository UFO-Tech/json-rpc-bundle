<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\ParamConvertors;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\ParamConvertors\BackedEnumConvertor;
use Ufo\JsonRpcBundle\Tests\Unit\MockBackedEnum;

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
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid value 'invalidValue' for enum");
        $mockEnumClass = MockBackedEnum::class;
        $context = ['classFQCN' => $mockEnumClass];
        $this->convertor->toObject('invalidValue', $context);
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

}