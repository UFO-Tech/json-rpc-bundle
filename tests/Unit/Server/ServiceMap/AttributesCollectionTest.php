<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\ServiceMap;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\Server\ServiceMap\AttributesCollection;

class AttributesCollectionTest extends TestCase
{
    public function testAddAndGetAttributesByClass(): void
    {
        $collection = new AttributesCollection();
        $first = new \stdClass();
        $second = new \stdClass();
        $third = new \ArrayObject();

        $collection->addAttribute($first);
        $collection->addAttribute($third);
        $collection->addAttribute($second);

        $attributes = $collection->getAttributes(\stdClass::class);

        $this->assertCount(2, $attributes);
        $this->assertSame($first, $attributes[0]);
        $this->assertSame($second, $attributes[1]);
    }

    public function testGetAttributeReturnsNullWhenPositionNotFound(): void
    {
        $collection = new AttributesCollection();
        $collection->addAttribute(new \ArrayObject());

        $this->assertNull($collection->getAttribute(\stdClass::class));
    }
}

