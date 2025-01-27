<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Date;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;

#[AutoconfigureTag('rpc.constraint')]
class IsDate implements IConstraintGenerator
{
    public function generate(Constraint $constraint, array &$rules): void
    {
        /**
         * @var Date $constraint
         */
        $rules['format'] = 'date';
    }

    public function getSupportedClass(): string
    {
        return Date::class;
    }
}
