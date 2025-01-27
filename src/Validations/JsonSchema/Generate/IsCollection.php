<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;

#[AutoconfigureTag('rpc.constraint')]
class IsCollection implements IConstraintGenerator
{
    public function generate(Constraint $constraint, array &$rules): void
    {
        $rules['type'] = 'object';
    }

    public function getSupportedClass(): string
    {
        return Collection::class;
    }
}