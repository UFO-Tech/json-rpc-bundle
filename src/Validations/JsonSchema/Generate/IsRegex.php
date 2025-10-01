<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Regex;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Traits\ConstraintApplier;

#[AutoconfigureTag('rpc.constraint')]
class IsRegex implements IConstraintGenerator
{
    use ConstraintApplier;

    public function getSupportedClass(): string
    {

        return Regex::class;
    }

    protected function apply(Constraint $constraint, array &$rules, ?Generator $generator = null): void
    {
        /**
         * @var Regex $constraint
         */
        $rules['pattern'] = $constraint->pattern;
    }

    protected function getSupportedTypes(): array
    {
        return [
            TypeHintResolver::STRING->value,
            TypeHintResolver::INT->value
        ];
    }

}