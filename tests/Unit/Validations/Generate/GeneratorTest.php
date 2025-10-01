<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\Validations\Generate;

use Symfony\Component\Validator\Constraints as Assert;
use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Generator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsAll;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsNotBlank;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsOptional;

class GeneratorTest extends TestCase
{
    private Generator $generator;

    protected function setUp(): void
    {
        $this->generator = new Generator([
            new IsNotBlank(),
            new IsAll(),
            new IsOptional(),
        ]);
    }

    public function testSingleNotBlankConstraint(): void
    {
        $rules = [
            'type' => 'string',
        ];
        $constraint = new Assert\NotBlank();

        $this->generator->dispatch($constraint, $rules);

        $this->assertArrayHasKey('minLength', $rules);
        $this->assertEquals(1, $rules['minLength']);
    }


    public function testOptionalConstraint(): void
    {
        $rules = [];
        $constraint = new Assert\Optional();

        $this->generator->dispatch($constraint, $rules);

        $this->assertArrayHasKey('optional', $rules);
    }
}
