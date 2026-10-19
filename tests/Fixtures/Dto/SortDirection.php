<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures\Dto;

enum SortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';
}
