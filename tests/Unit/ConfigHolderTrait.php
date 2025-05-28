<?php

namespace Ufo\JsonRpcBundle\Tests\Unit;

use Symfony\Component\HttpFoundation\RequestStack;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;

trait ConfigHolderTrait
{
    private RpcMainConfig $rpcMainConfig;

    protected function setConfig(array $config = []): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $this->rpcMainConfig = new RpcMainConfig($config,'test', $requestStack);
    }

    protected function setUpConfig(array $config = []): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $this->rpcMainConfig = new RpcMainConfig($config,'test', $requestStack);
    }
}