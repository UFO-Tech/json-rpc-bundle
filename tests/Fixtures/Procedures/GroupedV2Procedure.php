<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures\Procedures;

use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

#[RPC\Info('area', version: 2, removedMethods: ['privateMethod'])]
#[RPC\APIGroups(['bfa'], false)]
class GroupedV2Procedure implements IRpcService
{
    public function shared(): string { return 'v2'; }
}
