<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Email;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;

#[AutoconfigureTag('rpc.constraint')]
class IsEmail implements IConstraintGenerator
{
    public function generate(Constraint $constraint, array &$rules): void
    {
        /**
         * @var Email $constraint
         */
        $rules['format'] = 'email';
    }

    public function getSupportedClass(): string
    {
        return Email::class;
    }
}