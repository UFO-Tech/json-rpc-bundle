<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Traits\ConstraintApplier;

#[AutoconfigureTag('rpc.constraint')]
class IsCollection implements IConstraintGenerator
{
    use ConstraintApplier;

    public function getSupportedClass(): string
    {
        return Collection::class;
    }

    protected function apply(Constraint $constraint, array &$rules, ?Generator $generator = null): void
    {
        $rules['type'] = 'object';
    }

    protected function getSupportedTypes(): array
    {
        return [TypeHintResolver::ARRAY->value];
    }
}