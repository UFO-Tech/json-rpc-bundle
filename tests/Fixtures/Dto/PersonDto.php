<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures\Dto;

class PersonDto
{
    public function __construct(
        readonly public string $name,
        readonly public int $age,
    ) {}
}
