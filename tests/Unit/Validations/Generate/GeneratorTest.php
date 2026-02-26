<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\Validations\Generate;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Generator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsAll;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsNotBlank;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsOptional;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;

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

    public function testDispatchLogsWarningWhenGeneratorIsMissing(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains(Assert\NotNull::class))
        ;

        $generator = new Generator([], $logger);
        $rules = [];
        $generator->dispatch(new Assert\NotNull(), $rules);

        $this->assertSame([], $rules);
    }

    public function testDispatchSwallowsGeneratorExceptions(): void
    {
        $brokenGenerator = new class implements IConstraintGenerator {
            public function generate(Constraint $constraint, array &$rules, ?Generator $generator = null): void
            {
                throw new \RuntimeException('broken');
            }

            public function getSupportedClass(): string
            {
                return Assert\NotNull::class;
            }
        };

        $generator = new Generator([$brokenGenerator]);
        $rules = [];

        $generator->dispatch(new Assert\NotNull(), $rules);

        $this->assertSame([], $rules);
    }
}
