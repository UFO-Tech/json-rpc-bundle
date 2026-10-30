<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures\Dto;

/** Не enum: перелік для Choice віддає статичний метод звичайного класу. */
final class ShippingMethods
{
    /** @return string[] */
    public static function all(): array
    {
        return ['post', 'courier'];
    }
}
