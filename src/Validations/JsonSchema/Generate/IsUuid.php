<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Uuid;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Traits\ConstraintApplier;

#[AutoconfigureTag('rpc.constraint', attributes: [
    'priority' => 101,
])]
class IsUuid implements IConstraintGenerator
{
    use ConstraintApplier;

    public const int UUID_LENGTH = 36;

    public function getSupportedClass(): string
    {
        return Uuid::class;
    }

    protected function apply(Constraint $constraint, array &$rules, ?Generator $generator = null): void
    {
        $rules['format'] = 'uuid';
        $rules['minLength'] = $rules['maxLength'] = self::UUID_LENGTH;
    }

    protected function getSupportedTypes(): array
    {
        return [
            TypeHintResolver::STRING->value
        ];
    }
}