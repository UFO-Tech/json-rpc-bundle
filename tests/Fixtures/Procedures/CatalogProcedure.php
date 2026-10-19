<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures\Procedures;

use DateTimeImmutable;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\Tests\Fixtures\Dto\Color;
use Ufo\JsonRpcBundle\Tests\Fixtures\Dto\ItemDto;
use Ufo\JsonRpcBundle\Tests\Fixtures\Dto\Level;
use Ufo\JsonRpcBundle\Tests\Fixtures\Dto\LevelDto;
use Ufo\JsonRpcBundle\Tests\Fixtures\Dto\SortDirection;
use Ufo\RpcObject\RPC;

/** Parameters converted by IParamConvertor without #[RPC\Param], a DTO result. */
#[RPC\Info('catalog')]
class CatalogProcedure implements IRpcService
{
    public function item(int $id, Color $color = Color::Red): ItemDto
    {
        return new ItemDto($id, 'n'.$id, $color);
    }

    public function day(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d');
    }

    public function level(int $id, Level $level = Level::Zero): LevelDto
    {
        return new LevelDto($id, $level);
    }

    public function levelOf(LevelDto $dto): int
    {
        return $dto->level->value;
    }

    public function sortNullable(int $id, ?SortDirection $dir = null): array
    {
        return ['id' => $id, 'dir' => $dir?->value];
    }

    public function sortWithDefault(int $id, SortDirection $dir = SortDirection::Asc): array
    {
        return ['id' => $id, 'dir' => $dir->value];
    }

    public function dayOrNull(int $id, ?DateTimeImmutable $date = null): array
    {
        return ['id' => $id, 'date' => $date?->format('Y-m-d')];
    }
}
