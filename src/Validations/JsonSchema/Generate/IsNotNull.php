<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotNull;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Traits\ConstraintApplier;

#[AutoconfigureTag('rpc.constraint')]
class IsNotNull implements IConstraintGenerator
{
    use ConstraintApplier;


    public function getSupportedClass(): string
    {
        return NotNull::class;
    }

    protected function apply(Constraint $constraint, array &$rules, ?Generator $generator = null): void
    {
        /**
         * @var NotNull $constraint
         */
        $rules['not'] = ['type' => TypeHintResolver::NULL->value];
    }

    protected function getSupportedTypes(): array
    {
        return [TypeHintResolver::ANY->value];
    }
}