<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Range;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;

#[AutoconfigureTag('rpc.constraint')]
class IsRange implements IConstraintGenerator
{
    public function generate(Constraint $constraint, array &$rules): void
    {
        /**
         * @var Range $constraint
         */
        $rules += ['minimum' => $constraint->min, 'maximum' => $constraint->max];
    }

    public function getSupportedClass(): string
    {
        return Range::class;
    }
}