<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;


use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EqualTo;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generators\Interfaces\IConstraintGenerator;

#[AutoconfigureTag('rpc.constraint')]
class IsEqualTo implements IConstraintGenerator
{
    public function generate(Constraint $constraint, array &$rules): void
    {
        /**
         * @var EqualTo $constraint
         */
        $rules['const'] = $constraint->value;
    }

    public function getSupportedClass(): string
    {
        return EqualTo::class;
    }
}