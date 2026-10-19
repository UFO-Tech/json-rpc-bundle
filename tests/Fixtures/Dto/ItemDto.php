<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures\Dto;

final class ItemDto
{
    public function __construct(
        public int $id,
        public string $name,
        public Color $color = Color::Red,
    ) {}
}
