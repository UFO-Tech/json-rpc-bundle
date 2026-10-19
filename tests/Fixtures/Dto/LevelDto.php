<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures\Dto;

final class LevelDto
{
    public function __construct(
        public int $id,
        public Level $level = Level::Zero,
    ) {}
}
