<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use ReflectionException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Ufo\DTO\Helpers\EnumResolver;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Traits\ConstraintApplier;

#[AutoconfigureTag('rpc.constraint')]
class IsChoice implements IConstraintGenerator
{
    use ConstraintApplier;

    public function getSupportedClass(): string
    {
        return Choice::class;
    }

    /**
     * @param Constraint $constraint
     * @param array &$rules
     * @param Generator|null $generator
     * @throws ReflectionException
     */
    protected function apply(Constraint $constraint, array &$rules, ?Generator $generator = null): void
    {
        /**
         * @var Choice $constraint
         */
        $choices = $constraint->choices ?? [];
        $enumData[TypeHintResolver::TYPE] = is_string($choices[0] ?? '') ? TypeHintResolver::STRING->value : TypeHintResolver::INTEGER->value;
        $enumData[EnumResolver::ENUM_KEY] = $choices;

        if (is_array($constraint->callback) && count($constraint->callback) === 2) {
            [$enum, $method] = $constraint->callback;
            if (method_exists($enum, $method)) {
                $enumData = EnumResolver::generateEnumSchema($enum);
            }
        }

        $rules = TypeHintResolver::applyToSchema(
            $rules,
            function(array $schema) use ($enumData) {
                if (($schema[TypeHintResolver::TYPE] ?? '') === $enumData[TypeHintResolver::TYPE]) {
                    $schema = array_merge($schema, $enumData);
                }
                return $schema;
            }
        );

    }

    protected function getSupportedTypes(): array
    {
        return [TypeHintResolver::ANY->value];
    }
}