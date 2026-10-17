<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures\Procedures;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\RouterInterface;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

/**
 * Only in the "tools" group, so it does not change the method lists of the other groups.
 */
#[RPC\Info('tools', concat: '_')]
#[RPC\APIGroups(['tools'], false)]
class ToolsProcedure implements IRpcService
{
    public function router(#[Autowire(service: 'router')] RouterInterface $router): string
    {
        return $router::class;
    }

    public function greet(string $name, #[Autowire(service: 'router')] RouterInterface $router): string
    {
        return $name.':'.($router instanceof RouterInterface ? 'router' : 'none');
    }

    /** @deprecated use tools_greet */
    public function old(): string
    {
        return 'old';
    }
}
