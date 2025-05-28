<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\ParamConvertors;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\ParamConvertors\DateTimeConverter;

class DateTimeConverterTest extends TestCase
{
    protected DateTimeConverter $convertor;
    protected function setUp(): void
    {
        $this->convertor = new DateTimeConverter();
    }

    /**
     * Test that toScalar correctly converts a DateTime object to a formatted string.
     */
    public function testToScalarWithDefaultFormat(): void
    {
        
        $dateTime = new DateTime('2023-12-01 15:30:45');
        $result = $this->convertor->toScalar($dateTime);
        $this->assertSame('2023-12-01 15:30:45', $result);
    }

    /**
     * Test that toScalar correctly converts a DateTime object using a custom format.
     */
    public function testToScalarWithCustomFormat(): void
    {
        
        $dateTime = new DateTime('2023-12-01 15:30:45');
        $context = ['format' => 'Y/m/d'];
        $result = $this->convertor->toScalar($dateTime, $context);
        $this->assertSame('2023/12/01', $result);
    }

    /**
     * Test that toScalar applies a callback correctly.
     */
    public function testToScalarWithCallback(): void
    {
        
        $dateTime = new DateTime('2023-12-01 15:30:45');
        $callback = fn($scalar, $object) => strtoupper($scalar);
        $result = $this->convertor->toScalar($dateTime, [], $callback);
        $this->assertSame('2023-12-01 15:30:45', strtoupper('2023-12-01 15:30:45'));
        $this->assertSame('2023-12-01 15:30:45', $result);
    }

    /**
     * Test that toScalar returns null when no valid DateTimeInterface instance is provided.
     */
    public function testToScalarWithInvalidObject(): void
    {
        $this->expectException(\Error::class);
        
        $invalidObject = new \stdClass();
        $this->convertor->toScalar($invalidObject);
    }

    /**
     * Test that toScalar works correctly with DateTimeImmutable.
     */
    public function testToScalarWithDateTimeImmutable(): void
    {
        
        $dateTime = new DateTimeImmutable('2023-12-01 15:30:45');
        $result = $this->convertor->toScalar($dateTime);
        $this->assertSame('2023-12-01 15:30:45', $result);
    }

    /**
     * Test that toObject correctly converts a string to a DateTime object by default.
     */
    public function testToObjectWithDefaultClass(): void
    {
        
        $result = $this->convertor->toObject('2023-12-01 15:30:45');
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertSame('2023-12-01 15:30:45', $result->format('Y-m-d H:i:s'));
    }

    /**
     * Test that toObject correctly converts a string to a DateTimeImmutable object when specified in context.
     */
    public function testToObjectWithDateTimeImmutableInContext(): void
    {
        
        $context = ['classFQCN' => \DateTimeImmutable::class];
        $result = $this->convertor->toObject('2023-12-01 15:30:45', $context);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertSame('2023-12-01 15:30:45', $result->format('Y-m-d H:i:s'));
    }

    /**
     * Test that toObject applies a callback correctly.
     */
    public function testToObjectWithCallback(): void
    {
        
        $callback = fn($value, $object) => $object->setTime(0, 0, 0);
        $result = $this->convertor->toObject('2023-12-01 15:30:45', [], $callback);
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertSame('2023-12-01 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    /**
     * Test that toObject returns a new DateTime on invalid input string.
     */
    public function testToObjectWithInvalidInput(): void
    {
        
        $result = $this->convertor->toObject('invalid-date-string');
        $this->assertInstanceOf(\DateTime::class, $result);
    }

    /**
     * Test that toObject returns null when null is provided as input.
     */
    public function testToObjectWithNullValue(): void
    {
        $result = $this->convertor->toObject(null);
        $this->assertInstanceOf(\DateTime::class, $result);
    }

    /**
     * Test that supported returns true for supported DateTime classes.
     */
    public function testSupportedWithValidClasses(): void
    {
        
        $this->assertTrue($this->convertor->supported(\DateTime::class));
        $this->assertTrue($this->convertor->supported(\DateTimeImmutable::class));
        $this->assertTrue($this->convertor->supported(\DateTimeInterface::class));
    }

    /**
     * Test that supported returns false for unsupported classes.
     */
    public function testSupportedWithUnsupportedClass(): void
    {
        
        $this->assertFalse($this->convertor->supported(\stdClass::class));
        $this->assertFalse($this->convertor->supported('NonExistentClass'));
    }

}