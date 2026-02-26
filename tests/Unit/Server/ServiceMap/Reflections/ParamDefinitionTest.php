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

    public function testChangeTypeAndSchema(): void
    {
        $param = new ParamDefinition('ids', ['integer'], 'array');

        $param->changeType(['array'])->setSchema(['type' => 'array', 'items' => ['type' => 'integer']]);

        $this->assertSame(['array'], $param->getType());
        $this->assertSame(['type' => 'array', 'items' => ['type' => 'integer']], $param->getSchema());
    }
}

