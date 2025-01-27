<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;

#[AutoconfigureTag('rpc.constraint')]
class IsGreaterThan implements IConstraintGenerator
{
    public function generate(Constraint $constraint, array &$rules): void
    {
        /**
         * @var GreaterThan $constraint
         */
        $rules['exclusiveMinimum'] = $constraint->value;
    }

    public function getSupportedClass(): string
    {
        return GreaterThan::class;
    }
}