<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Traits\ConstraintApplier;

#[AutoconfigureTag('rpc.constraint', attributes: [
    'priority' => 100,
])]
class IsAll implements IConstraintGenerator
{
    use ConstraintApplier;

    public function getSupportedClass(): string
    {
        return All::class;
    }

    protected function apply(Constraint $constraint, array &$rules, ?Generator $generator = null): void
    {
        /**
         * @var All $constraint
         */
        if ($generator) {
            foreach ($constraint->constraints as $constraintItem) {
                $generator->dispatch($constraintItem, $rules);
            }
        }
        $rules['type'] = TypeHintResolver::ARRAY->value;
    }

    protected function getSupportedTypes(): array
    {
        return [
            TypeHintResolver::ARRAY->value
        ];
    }
}
