<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces;


use Symfony\Component\Validator\Constraint;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Generator;

interface IConstraintGenerator
{
    const int PRIORITY = 0;

    public function generate(Constraint $constraint, array &$rules, ?Generator $generator = null): void;

    public function getSupportedClass(): string;
}