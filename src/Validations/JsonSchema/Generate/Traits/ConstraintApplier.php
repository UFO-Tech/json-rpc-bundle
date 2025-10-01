<?php

declare(strict_types = 1);

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Traits;

use Symfony\Component\Validator\Constraint;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Generator;

trait ConstraintApplier
{
    public function generate(Constraint $constraint, array &$rules, ?Generator $generator = null): void
    {
        $type = $rules[TypeHintResolver::TYPE] ?? TypeHintResolver::ANY->value;

        if (in_array($type, $this->getSupportedTypes(), true) || in_array(TypeHintResolver::ANY->value, $this->getSupportedTypes(), true)) {
            $this->apply($constraint, $rules, $generator);
        }
    }

    abstract protected function apply(Constraint $constraint, array &$rules, ?Generator $generator = null): void;

    abstract protected function getSupportedTypes(): array;
}