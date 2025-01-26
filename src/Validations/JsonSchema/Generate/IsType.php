<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Type;
use Ufo\RpcObject\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;

use function is_string;

#[AutoconfigureTag('rpc.constraint')]
class IsType implements IConstraintGenerator
{
    public function generate(Constraint $constraint, array &$rules): void
    {
        /**
         * @var Type $constraint
         */
        $phpType = is_string($constraint->type) ? $constraint->type : 'object';

        $jsonSchemaType = TypeHintResolver::phpToJsonSchema($phpType);
        if (empty($jsonSchemaType)) {
            $rules['oneOf'] = TypeHintResolver::mixedForJsonSchema();
        } else {
            $rules['type'] = $jsonSchemaType;
        }
    }

    public function getSupportedClass(): string
    {
        return Type::class;
    }
}