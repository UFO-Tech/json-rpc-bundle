<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces;


use Symfony\Component\Validator\Constraint;

interface IConstraintGenerator
{
    public function generate(Constraint $constraint, array &$rules): void;

    public function getSupportedClass(): string;
}