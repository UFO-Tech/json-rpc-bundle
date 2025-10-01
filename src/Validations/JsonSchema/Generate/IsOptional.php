<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Optional;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Traits\ConstraintApplier;

#[AutoconfigureTag('rpc.constraint', attributes: [
    'priority' => 101,
])]
class IsOptional implements IConstraintGenerator
{
    use ConstraintApplier;

    public function getSupportedClass(): string
    {
        return Optional::class;
    }

    protected function apply(Constraint $constraint, array &$rules, ?Generator $generator = null): void
    {
        /**
         * @var Optional $constraint
         */
        $rules['optional'] = true;
    }

    protected function getSupportedTypes(): array
    {
        return [TypeHintResolver::ANY->value];
    }
}
