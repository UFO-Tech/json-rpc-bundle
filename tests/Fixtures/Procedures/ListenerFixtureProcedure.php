<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures\Procedures;

use RuntimeException;
use stdClass;
use TypeError;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\Tests\Fixtures\Dto\PersonDto;

class ListenerFixtureProcedure implements IRpcService
{
    public function ping(string $name, int $times = 1): string
    {
        return $name . ':' . $times;
    }

    public function triple(string $a, string $b, string $c): string
    {
        return $a . $b . $c;
    }

    public function injected(string $name, stdClass $dependency, int $times = 5): string
    {
        return $name . ':' . $times;
    }

    public function expectInt(int $id): int
    {
        return $id;
    }

    public function expectDto(PersonDto $person): string
    {
        return $person->name;
    }

    public function throwTypeError(): void
    {
        throw new TypeError('custom type failure');
    }

    public function throwRuntime(): void
    {
        throw new RuntimeException('kaboom');
    }
}
