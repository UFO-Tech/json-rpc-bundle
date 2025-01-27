<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Uuid;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;

#[AutoconfigureTag('rpc.constraint')]
class IsUuid implements IConstraintGenerator
{
    public const int UUID_LENGTH = 36;

    public function generate(Constraint $constraint, array &$rules): void
    {
        $rules['format'] = 'uuid';
        $rules['minLength'] = $rules['maxLength'] = self::UUID_LENGTH;
    }
    
    public function getSupportedClass(): string
    {
        return Uuid::class;
    }
}