<?php

namespace Ufo\JsonRpcBundle\Locker;

use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcError\RpcLogicException;
use Ufo\RpcObject\RPC\Lock;
use Ufo\RpcObject\RpcRequest;

class LockerService
{
    protected LockFactory $lockFactory;
    protected ?LockInterface $lockInstance = null;

    public function __construct(
        UfoLockerStore $flockStore,
    )
    {
        $this->lockFactory = new LockFactory($flockStore);
    }

    public function lock(RpcRequest $request, Service $service): void
    {
        if (!$lock = $service->getAttrCollection()->getAttribute(Lock::class)) return;

        try {
            $this->lockInstance = $lock->acquire($request, $this->lockFactory);
        } catch (LockConflictedException $e) {
            throw new RpcLogicException("Method '{$request->getMethod()}' is locked.", previous: $e);
        }
    }

    public function release(): void
    {
        $this->lockInstance?->release();
    }

}