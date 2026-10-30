<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures\Procedures;

use Symfony\Component\Validator\Constraints as Assert;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\Tests\Fixtures\Dto\Color;
use Ufo\JsonRpcBundle\Tests\Fixtures\Dto\ItemDto;
use Ufo\JsonRpcBundle\Tests\Fixtures\Dto\Level;
use Ufo\JsonRpcBundle\Tests\Fixtures\Namesake\Color as NamesakeColor;
use Ufo\JsonRpcBundle\Tests\Fixtures\Namesake\ItemDto as NamesakeItemDto;
use Ufo\JsonRpcBundle\Tests\Fixtures\Namesake\Level as LevelDto;
use Ufo\RpcObject\RPC;

/** Тезки enum ↔ enum, DTO ↔ DTO, enum ↔ DTO і тезка через Choice. */
#[RPC\Info('namesakes')]
class NamesakeDocProcedure implements IRpcService
{
    public function colors(Color $color, NamesakeColor $finish): NamesakeColor
    {
        return $finish;
    }

    public function items(ItemDto $item, NamesakeItemDto $other): NamesakeItemDto
    {
        return $other;
    }

    public function levels(Level $level, LevelDto $floor): LevelDto
    {
        return $floor;
    }

    public function choice(
        #[RPC\Assertions([new Assert\Choice(callback: [NamesakeColor::class, 'cases'])])]
        string $finish,
    ): bool {
        return true;
    }
}
