<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures\Procedures;

use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\Tests\Fixtures\Dto\Color;
use Ufo\JsonRpcBundle\Tests\Fixtures\Dto\Level;
use Ufo\RpcObject\RPC;

/** Enum у результатах і в параметрах різної форми: усі мають бути $ref на компонент. */
#[RPC\Info('enums')]
class EnumDocProcedure implements IRpcService
{
    public function one(Color $color): Color
    {
        return $color;
    }

    /** @return Color[] */
    public function list(): array
    {
        return [Color::Red, Color::Blue];
    }

    public function maybe(?Level $level = null): ?Level
    {
        return $level;
    }

    public function union(Color|Level $value): bool
    {
        return true;
    }

    /** @param array<string, Color> $map */
    public function map(array $map): bool
    {
        return true;
    }
}
