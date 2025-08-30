<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use ReflectionException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;

use function implode;

#[AutoconfigureTag('rpc.constraint')]
class IsChoice implements IConstraintGenerator
{
    /**
     * @throws ReflectionException
     */
    public function generate(Constraint $constraint, array &$rules): void
    {
        /**
         * @var Choice $constraint
         */
        $choices = $constraint->choices ?? [];
        if (is_array($constraint->callback) && count($constraint->callback) === 2) {
            [$enum, $method] = $constraint->callback;
            if (method_exists($enum, $method)) {
                $choices = $enum::$method();
            }
        }

        if (isset($rules[TypeHintResolver::ONE_OFF])) {
            foreach ($rules[TypeHintResolver::ONE_OFF] as $i => $schema) {
                if (($schema[TypeHintResolver::TYPE] ?? '') === gettype($choices[0])) {
                    $rules[TypeHintResolver::ONE_OFF][$i]['enum'] = $choices;
                    break;
                }
            }
        } else {
            $rules['enum'] = $choices;
        }
    }

    public function getSupportedClass(): string
    {
        return Choice::class;
    }
}