<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\Validations\Generate;

use Symfony\Component\Validator\Constraints as Assert;
use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Genearator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsAll;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsNotBlank;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsOptional;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsType;

class GeneratorTest extends TestCase
{
    private Genearator $generator;

    protected function setUp(): void
    {
        $this->generator = new Genearator([
            new IsType(),
            new IsNotBlank(),

            new IsAll(),
            new IsOptional(),
        ]);
    }

    public function testSingleTypeConstraint(): void
    {
        $rules = [];
        $constraint = new Assert\Type('string');

        $this->generator->dispatch($constraint, $rules);

        $this->assertArrayHasKey('type', $rules);
        $this->assertEquals('string', $rules['type']);
    }

    public function testSingleNotBlankConstraint(): void
    {
        $rules = [];
        $constraint = new Assert\NotBlank();

        $this->generator->dispatch($constraint, $rules);

        $this->assertArrayHasKey('minLength', $rules);
        $this->assertEquals(1, $rules['minLength']);
    }

    public function testCompositeAllConstraint(): void
    {
        $rules = [];
        $constraint = new Assert\All([
            new Assert\Type('integer'),
            new Assert\NotBlank(),
        ]);

        $this->generator->dispatch($constraint, $rules);

        $this->assertArrayHasKey('items', $rules);
        $this->assertEquals([
            'type' => 'integer',
            'minLength' => 1,
        ], $rules['items']);
    }


    public function testOptionalConstraint(): void
    {
        $rules = [];
        $constraint = new Assert\Optional([
            new Assert\Type('string'),
        ]);

        $this->generator->dispatch($constraint, $rules);

        $this->assertArrayHasKey('type', $rules);
        $this->assertEquals('string', $rules['type']);
    }

    public function testInfiniteRecursionPrevention(): void
    {
        $rules = [];

        $recursiveConstraint = new Assert\All([
            new Assert\Type('integer'),
        ]);

        $recursiveConstraint->constraints[] = $recursiveConstraint;

        $this->generator->dispatch($recursiveConstraint, $rules);

        $this->assertArrayHasKey('items', $rules);
        $this->assertNotEmpty($rules['items']);
        $this->assertEquals([
            'type' => 'integer',
        ], $rules['items']);
    }
}
