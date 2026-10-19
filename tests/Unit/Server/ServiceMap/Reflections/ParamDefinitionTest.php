<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\ServiceMap\Reflections;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;

class ParamDefinitionTest extends TestCase
{
    public function testSetDefaultMarksOptionalForNonNullValue(): void
    {
        $param = new ParamDefinition('limit', ['integer'], 'int');
        $param->setDefault(10);

        $this->assertTrue($param->isOptional());
        $this->assertSame(10, $param->getDefault());
    }

    public function testSetDefaultWithNullDoesNotMarkOptionalForNonNullableType(): void
    {
        $param = new ParamDefinition('name', ['string'], 'string');
        $param->setDefault(null);

        $this->assertFalse($param->isOptional());
        $this->assertNull($param->getDefault());
    }

    public function testSetDefaultWithNullMarksOptionalForNullableType(): void
    {
        $param = new ParamDefinition('name', ['string', 'null'], '?string');
        $param->setDefault(null);

        $this->assertTrue($param->isOptional());
        $this->assertNull($param->getDefault());
    }

    public function testAcceptsNullForNullableTypes(): void
    {
        $this->assertTrue((new ParamDefinition('dir', ['string'], ['App\\SortDirection', 'null']))->acceptsNull());
        $this->assertTrue((new ParamDefinition('name', ['string'], '?string'))->acceptsNull());
        $this->assertTrue((new ParamDefinition('any', ['string'], 'mixed'))->acceptsNull());
        $this->assertTrue((new ParamDefinition('dir', ['string'], ['App\\SortDirection', 'null']))->setDefault(null)->acceptsNull());
    }

    public function testDoesNotAcceptNullForNonNullableTypes(): void
    {
        $this->assertFalse((new ParamDefinition('dir', ['string'], 'App\\SortDirection'))->acceptsNull());
        $this->assertFalse((new ParamDefinition('dir', ['string'], 'App\\SortDirection'))->setDefault('asc')->acceptsNull());
        $this->assertFalse((new ParamDefinition('name', ['string'], 'string'))->setDefault(null)->acceptsNull());
    }

    public function testNonNullTypeIsTheSingleTypeWithoutNull(): void
    {
        $this->assertSame('App\\SortDirection', (new ParamDefinition('dir', ['string'], 'App\\SortDirection'))->getNonNullType());
        $this->assertSame('App\\SortDirection', (new ParamDefinition('dir', ['string'], ['App\\SortDirection', 'null']))->getNonNullType());
        $this->assertNull((new ParamDefinition('id', ['string'], ['App\\A', 'App\\B']))->getNonNullType());
        $this->assertNull((new ParamDefinition('id', ['string'], ['App\\A', 'App\\B', 'null']))->getNonNullType());
    }

    public function testChangeTypeAndSchema(): void
    {
        $param = new ParamDefinition('ids', ['integer'], 'array');

        $param->changeType(['array'])->setSchema(['type' => 'array', 'items' => ['type' => 'integer']]);

        $this->assertSame(['array'], $param->getType());
        $this->assertSame(['type' => 'array', 'items' => ['type' => 'integer']], $param->getSchema());
    }
}

