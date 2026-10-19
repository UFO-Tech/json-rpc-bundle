<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures\Procedures;

use DateTimeImmutable;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\Tests\Fixtures\Dto\Color;
use Ufo\JsonRpcBundle\Tests\Fixtures\Dto\ItemDto;
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
}
