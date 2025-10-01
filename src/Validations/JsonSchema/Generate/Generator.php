<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Validator\Constraint;
use Psr\Log\LoggerInterface;
use Throwable;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Enums\CompositeConstraintType;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Interfaces\IConstraintGenerator;

use function spl_object_hash;
use function array_merge;

class Generator
{
    /**
     * @var array<string, IConstraintGenerator> $constraints
     */
    protected iterable $constraints = [];

    public function __construct(
        #[AutowireIterator('rpc.constraint')]
        iterable $constraints = [],
        private ?LoggerInterface $logger = null
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
            if (!isset($this->constraints[$constraint::class])) {
                $this->logger?->warning(sprintf(
                    'No generator found for constraint: %s',
                    $constraint::class
                ));
                return;
            }

            $this->constraints[$constraint::class]->generate($constraint, $rules, $this);
        } catch (Throwable $exception) {}

        unset($processed[$hash]);
    }
}