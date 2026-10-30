<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures\Procedures;

use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

/**
 * Лише в групі "internal_dev_2": підкреслення й цифра в назві групи.
 */
#[RPC\Info('internal')]
#[RPC\APIGroups(['internal_dev_2'], false)]
class InternalDevProcedure implements IRpcService
{
    public function status(): string
    {
        return 'internal_dev_2';
    }
}
