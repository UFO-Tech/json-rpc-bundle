<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures\Dto;

class ProductDto
{
    public function __construct(
        readonly public string $sku,
    ) {}
}
