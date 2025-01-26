<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Validator\Constraint;
use Throwable;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Enums\CompositeConstraintType;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;

use function spl_object_hash;
use function array_merge;

class Genearator
{
    /**
     * @param iterable|IConstraintGenerator[] $constraints
     */
    protected iterable $constraints = [];

    /**
     * @param iterable|IConstraintGenerator[] $constraints
     */
    public function __construct(
        #[AutowireIterator('rpc.constraint')]
        iterable $constraints = []
    ) {
        foreach ($constraints as $generator) {
            $this->constraints[$generator->getSupportedClass()] = $generator;
        }
    }

    public function dispatch(Constraint $constraint, array &$rules): void
    {
        static $processed = [];

        // prevent inf recursion
        $hash = spl_object_hash($constraint);
        if (isset($processed[$hash])) {
            return;
        }
        $processed[$hash] = true;

        try {
            $this->constraints[$constraint::class]->generate($constraint, $rules);
            $this->processNestedConstraints($constraint, $rules);
        } catch (Throwable) {
        }

        unset($processed[$hash]);
    }

    private function processNestedConstraints(Constraint $constraint, array &$rules): void
    {
        if ($compositeType = CompositeConstraintType::fromConstraint($constraint)) {

            $nestedRules = $this->getNestedRules($constraint);

            $ruleKey = match ($compositeType) {
                CompositeConstraintType::ALL => 'items',
                CompositeConstraintType::COLLECTION => 'properties',
                CompositeConstraintType::OPTIONAL => null,
            };

            if ($ruleKey) {
                $rules[$ruleKey] = $nestedRules;
            } else {
                $rules = array_merge($rules, $nestedRules);
            }
        }
    }

    private function getNestedRules(Constraint $constraint): array
    {
        $nestedRules = [];
        foreach ($constraint->constraints as $inner) {
            $this->dispatch($inner, $nestedRules);
        }
        return $nestedRules;
    }
}