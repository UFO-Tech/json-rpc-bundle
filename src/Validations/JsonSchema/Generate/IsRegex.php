<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Regex;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;

#[AutoconfigureTag('rpc.constraint')]
class IsRegex implements IConstraintGenerator
{
    public function generate(Constraint $constraint, array &$rules): void
    {
        /**
         * @var Regex $constraint
         */
        $rules['pattern'] = $constraint->pattern;
    }

    public function getSupportedClass(): string
    {
        return Regex::class;
    }
}