<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\EqualTo;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Traits\ConstraintApplier;

#[AutoconfigureTag('rpc.constraint')]
class IsEqualTo implements IConstraintGenerator
{
    use ConstraintApplier;

    public function getSupportedClass(): string
    {
        return EqualTo::class;
    }

    protected function apply(Constraint $constraint, array &$rules, ?Generator $generator = null): void
    {
        /**
         * @var EqualTo $constraint
         */
        $rules['const'] = $constraint->value;
    }

    protected function getSupportedTypes(): array
    {
        return [TypeHintResolver::ANY->value];
    }
}