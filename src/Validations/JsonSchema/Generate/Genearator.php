<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;


use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Validator\Constraint;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generators\Interfaces\IConstraintGenerator;

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
        #[TaggedIterator('rpc.constraint')]
        iterable $constraints = []
    ) {
        foreach ($constraints as $generator) {
            $this->constraints[$generator->getSupportedClass()] = $generator;
        }
    }

    public function dispatch(Constraint $constraint, array &$rules): void
    {
        try {
            $this->constraints[$constraint::class]->generate($constraint, $rules);
        } catch (\Throwable) {
        }
    }
}