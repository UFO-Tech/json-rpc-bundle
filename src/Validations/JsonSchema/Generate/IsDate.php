<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Date;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Traits\ConstraintApplier;

#[AutoconfigureTag('rpc.constraint')]
class IsDate implements IConstraintGenerator
{
    use ConstraintApplier;

    public function getSupportedClass(): string
    {
        return Date::class;
    }

    protected function apply(Constraint $constraint, array &$rules, ?Generator $generator = null): void
    {
        /**
         * @var Date $constraint
         */
        $rules['format'] = 'date';
    }

    protected function getSupportedTypes(): array
    {
        return [TypeHintResolver::STRING->value];
    }
}
