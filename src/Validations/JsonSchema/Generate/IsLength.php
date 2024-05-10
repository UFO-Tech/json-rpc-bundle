<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;

use function is_null;

#[AutoconfigureTag('rpc.constraint')]
class IsLength implements IConstraintGenerator
{
    public function generate(Constraint $constraint, array &$rules): void
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

    public function getSupportedClass(): string
    {
        return Length::class;
    }
}