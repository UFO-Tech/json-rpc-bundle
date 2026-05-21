<?php

declare(strict_types = 1);

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;

use Ufo\DTO\Interfaces\DTOFromArrayTransformerInterface;
use Ufo\DTO\Interfaces\DTOToArrayTransformerInterface;

class DTOTransformerInitListener
{
    public function __construct(
        protected DTOFromArrayTransformerInterface $fromArrayTransformer,
        protected DTOToArrayTransformerInterface $toArrayTransformer,
    ) {}

}