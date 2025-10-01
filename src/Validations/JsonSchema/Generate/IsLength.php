<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;

use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Traits\ConstraintApplier;
use function is_null;

#[AutoconfigureTag('rpc.constraint')]
class IsLength implements IConstraintGenerator
{
    use ConstraintApplier;

    public function getSupportedClass(): string
    {
        return Length::class;
    }

    protected function apply(Constraint $constraint, array &$rules, ?Generator $generator = null): void
    {
        /**
         * @var Length $constraint
         */
        if (!is_null($constraint->min)) {
            $rules['minLength'] = $constraint->min;
        }
        if (!is_null($constraint->max)) {
            $rules['maxLength'] = $constraint->max;
        }
    }

    protected function getSupportedTypes(): array
    {
        return [TypeHintResolver::STRING->value];
    }
}