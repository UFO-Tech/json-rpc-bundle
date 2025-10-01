<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Range;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Traits\ConstraintApplier;

#[AutoconfigureTag('rpc.constraint')]
class IsRange implements IConstraintGenerator
{
    use ConstraintApplier;

    public function getSupportedClass(): string
    {
        return Range::class;
    }

    protected function apply(Constraint $constraint, array &$rules, ?Generator $generator = null): void
    {
        /**
         * @var Range $constraint
         */
        $rules += ['minimum' => $constraint->min, 'maximum' => $constraint->max];
    }

    protected function getSupportedTypes(): array
    {
        return [
            TypeHintResolver::INT->value,
            TypeHintResolver::INTEGER->value,
            TypeHintResolver::DOUBLE->value,
            TypeHintResolver::NUMBER->value,
            TypeHintResolver::FLOAT->value
        ];
    }
}