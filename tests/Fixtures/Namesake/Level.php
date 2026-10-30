<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures\Namesake;

/** DTO з тією самою короткою назвою, що й enum Fixtures\Dto\Level. */
final class Level
{
    public function __construct(
        public int $floor,
    ) {}
}
