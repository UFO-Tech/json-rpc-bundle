<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\Validations\Generate;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;
use Ufo\DTO\Helpers\EnumResolver;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Enums\CompositeConstraintType;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsChoice;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsCollection;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsDate;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsEmail;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsEqualTo;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsGreaterThan;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsGreaterThanOrEqual;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsLength;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsNotNull;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsRange;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsRegex;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\IsUuid;

class OtherGeneratorsTest extends TestCase
{
    public function testIsChoiceGeneratesEnumSchemaForStringChoices(): void
    {
        $generator = new IsChoice();
        $rules = ['type' => 'string'];

        $generator->generate(new Assert\Choice(['choices' => ['a', 'b']]), $rules);

        $this->assertSame(Assert\Choice::class, $generator->getSupportedClass());
        $this->assertSame('string', $rules['type']);
        $this->assertSame(['a', 'b'], $rules[EnumResolver::ENUM_KEY]);
    }

    public function testIsCollectionSetsObjectTypeOnlyForArrayRules(): void
    {
        $generator = new IsCollection();
        $rules = ['type' => 'array'];
        $generator->generate(new Assert\Collection([]), $rules);

        $this->assertSame(Assert\Collection::class, $generator->getSupportedClass());
        $this->assertSame('object', $rules['type']);
    }

    public function testDateAndEmailGeneratorsSetExpectedFormats(): void
    {
        $date = new IsDate();
        $email = new IsEmail();

        $dateRules = ['type' => 'string'];
        $emailRules = ['type' => 'string'];

        $date->generate(new Assert\Date(), $dateRules);
        $email->generate(new Assert\Email(), $emailRules);

        $this->assertSame(Assert\Date::class, $date->getSupportedClass());
        $this->assertSame(Assert\Email::class, $email->getSupportedClass());
        $this->assertSame('date', $dateRules['format']);
        $this->assertSame('email', $emailRules['format']);
    }

    public function testEqualAndNumericGeneratorsSetBounds(): void
    {
        $eq = new IsEqualTo();
        $gt = new IsGreaterThan();
        $gte = new IsGreaterThanOrEqual();
        $range = new IsRange();

        $eqRules = [];
        $numRules = ['type' => 'int'];

        $eq->generate(new Assert\EqualTo(['value' => 10]), $eqRules);
        $gt->generate(new Assert\GreaterThan(['value' => 5]), $numRules);
        $gte->generate(new Assert\GreaterThanOrEqual(['value' => 6]), $numRules);
        $range->generate(new Assert\Range(['min' => 1, 'max' => 9]), $numRules);

        $this->assertSame(10, $eqRules['const']);
        $this->assertSame(5, $numRules['exclusiveMinimum']);
        $this->assertSame(6, $numRules['minimum']);
        $this->assertSame(9, $numRules['maximum']);
    }

    public function testLengthRegexNotNullAndUuidGenerators(): void
    {
        $length = new IsLength();
        $regex = new IsRegex();
        $notNull = new IsNotNull();
        $uuid = new IsUuid();

        $lengthRules = ['type' => 'string'];
        $regexRules = ['type' => 'string'];
        $uuidRules = ['type' => 'string'];
        $anyRules = [];

        $length->generate(new Assert\Length(['min' => 2, 'max' => 8]), $lengthRules);
        $regex->generate(new Assert\Regex('/^[a-z]+$/'), $regexRules);
        $uuid->generate(new Assert\Uuid(), $uuidRules);
        $notNull->generate(new Assert\NotNull(), $anyRules);

        $this->assertSame(2, $lengthRules['minLength']);
        $this->assertSame(8, $lengthRules['maxLength']);
        $this->assertSame('/^[a-z]+$/', $regexRules['pattern']);
        $this->assertSame('uuid', $uuidRules['format']);
        $this->assertSame(IsUuid::UUID_LENGTH, $uuidRules['minLength']);
        $this->assertSame(IsUuid::UUID_LENGTH, $uuidRules['maxLength']);
        $this->assertSame(['type' => 'null'], $anyRules['not']);
    }

    public function testCompositeConstraintTypeFromConstraint(): void
    {
        $this->assertSame(
            CompositeConstraintType::ALL,
            CompositeConstraintType::fromConstraint(new Assert\All(['constraints' => [new Assert\NotBlank()]]))
        );
        $this->assertNull(CompositeConstraintType::fromConstraint(new Assert\NotBlank()));
    }
}
