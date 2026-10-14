<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\Server;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;

class ApiGroupContextTest extends TestCase
{
    public function testExplicitGroupAndRoutePrefixAreReplacedAndReset(): void
    {
        $context = new ApiGroupContext();
        self::assertSame('general', $context->getGroup());
        $context->setGroup('bfa', 'bfa_custom_');
        self::assertSame('bfa', $context->getGroup());
        self::assertSame('bfa_custom_ufo_rpc_api_server', $context->getRouteName('ufo_rpc_api_server'));
        $context->setGroup('bff');
        self::assertSame('bff_ufo_rpc_api_server', $context->getRouteName('ufo_rpc_api_server'));
        $context->reset();
        self::assertSame('general', $context->getGroup());
        self::assertSame('general_ufo_rpc_api_server', $context->getRouteName('ufo_rpc_api_server'));
    }
}
