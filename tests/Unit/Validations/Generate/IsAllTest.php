<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\Validations\Generate;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Generator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsAll;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsNotBlank;

class IsAllTest extends TestCase
{
    public function testGetSupportedClass(): void
    {
        $generator = new IsAll();

        $this->assertSame(Assert\All::class, $generator->getSupportedClass());
    }

    public function testGenerateDispatchesInnerConstraintsAndSetsArrayType(): void
    {
        $isAll = new IsAll();
        $dispatch = new Generator([new IsNotBlank()]);
        $rules = ['type' => 'array'];

        $isAll->generate(new Assert\All([
            'constraints' => [new Assert\NotBlank()],
        ]), $rules, $dispatch);

        $this->assertSame('array', $rules['type']);
        $this->assertArrayNotHasKey('minLength', $rules);
    }

    public function testGenerateDoesNothingForUnsupportedCurrentType(): void
    {
        $isAll = new IsAll();
        $rules = ['type' => 'string'];

        $isAll->generate(new Assert\All([
            'constraints' => [new Assert\NotBlank()],
        ]), $rules, null);

        $this->assertSame(['type' => 'string'], $rules);
    }
}
