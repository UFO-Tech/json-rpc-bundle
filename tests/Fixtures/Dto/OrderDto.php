<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures\Dto;

class OrderDto
{
    public function __construct(
        readonly public PersonDto $customer,
    ) {}
}
