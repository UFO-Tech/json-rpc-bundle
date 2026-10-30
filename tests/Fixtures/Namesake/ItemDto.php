<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures\Namesake;

final class ItemDto
{
    public function __construct(
        public string $label,
        public Color $finish = Color::Matte,
    ) {}
}
