<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\ParamConvertors;

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\UuidV4;
use Ufo\JsonRpcBundle\ParamConvertors\UuidConverter;

class UuidConverterTest extends TestCase
{
    private UuidConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new UuidConverter();
    }

    public function testToObjectWithValidUuidStringAndStandardClass(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $result = $this->converter->toObject($uuidString, ['classFQCN' => Uuid::class]);
        $this->assertInstanceOf(UuidInterface::class, $result);
        $this->assertEquals($uuidString, $result->toString());
    }

    public function testToObjectWithValidUuidStringAndInterface(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $result = $this->converter->toObject($uuidString, ['classFQCN' => UuidInterface::class]);
        $this->assertInstanceOf(UuidInterface::class, $result);
        $this->assertEquals($uuidString, $result->toString());
    }

    public function testToObjectWithValidUuidStringAndAbstractUid(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $result = $this->converter->toObject($uuidString, ['classFQCN' => UuidV4::class]);
        $this->assertInstanceOf(UuidV4::class, $result);
        $this->assertEquals($uuidString, $result->toRfc4122());
    }

    public function testToObjectWithUnsupportedClassThrowsException(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No convertor found for "550e8400-e29b-41d4-a716-446655440000" and classFQCN: Unsupported\Class');
        $this->converter->toObject($uuidString, ['classFQCN' => 'Unsupported\Class']);
    }

    public function testToObjectWithInvalidClassFQCNThrowsException(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No convertor found for "550e8400-e29b-41d4-a716-446655440000" and classFQCN: ');
        $this->converter->toObject($uuidString);
    }

    /**
     * Test that the supported method returns true for a class in the supports map.
     */
    public function testSupportedWithSupportedClass(): void
    {
        $this->assertTrue($this->converter->supported(\Ramsey\Uuid\Uuid::class));
    }

    /**
     * Test that the supported method returns false for a class not in the supports map.
     */
    public function testSupportedWithUnSupportedClass(): void
    {
        $this->assertFalse($this->converter->supported('Unsupported\Class'));
    }

    /**
     * Test that toScalar correctly converts a valid UUID object to its string representation.
     */
    public function testToScalarWithValidUuidObject(): void
    {
        $uuid = \Ramsey\Uuid\Uuid::fromString('550e8400-e29b-41d4-a716-446655440000');
        $result = $this->converter->toScalar($uuid);
        $this->assertIsString($result);
        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $result);
    }

    /**
     * Test that toScalar throws an exception when passed an invalid object.
     */
    public function testToScalarWithInvalidObjectThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->converter->toScalar(new class { });
    }

    /**
     * Test that toScalar falls back to string conversion when the object implements Stringable.
     */
    public function testToScalarWithFallbackStringConversion(): void
    {
        $stringableObject = new class implements \Stringable
        {
            public function __toString(): string
            {
                return 'StringableObject';
            }

        };
        $result = $this->converter->toScalar($stringableObject);
        $this->assertIsString($result);
        $this->assertEquals('StringableObject', $result);
    }

}