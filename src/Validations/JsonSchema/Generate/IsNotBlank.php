<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;


use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generators\Interfaces\IConstraintGenerator;

#[AutoconfigureTag('rpc.constraint')]
class IsNotBlank implements IConstraintGenerator
{
    public function generate(Constraint $constraint, array &$rules): void
    {
        $rules['minLength'] = 1;
    }

    public function getSupportedClass(): string
    {
        return NotBlank::class;
    }
}