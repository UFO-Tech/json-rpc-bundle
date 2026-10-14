<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures\Procedures;

use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

#[RPC\Info('area')]
#[RPC\APIGroups(['bfa', 'bff'])]
class GroupedProcedure implements IRpcService
{
    public static int $privateExecutions = 0;

    public function shared(): string { return 'shared'; }

    #[RPC\APIGroups(['bfa'], false)]
    public function privateMethod(): string { ++self::$privateExecutions; return 'bfa'; }

    #[RPC\APIGroups()]
    public function generalOnly(): string { return 'general'; }

    #[RPC\APIGroups(['bfa'], false)]
    #[RPC\IgnoreApi]
    public function ignored(): string { return 'ignored'; }

    #[RPC\APIGroups([], false)]
    public function nowhere(): string { return 'nowhere'; }
}
