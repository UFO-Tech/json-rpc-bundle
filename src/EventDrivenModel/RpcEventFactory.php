<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Ufo\RpcObject\Events\BaseRpcEvent;
use Ufo\RpcObject\Events\RpcAsyncRequestEvent;
use Ufo\RpcObject\Events\RpcErrorEvent;
use Ufo\RpcObject\Events\RpcEvent;
use Ufo\RpcObject\Events\RpcRequestEvent;
use Ufo\RpcObject\RpcRequest;

class RpcEventFactory
{
    /**
     * @var RpcEvent[]
     */
    protected array $eventsPool = [];

    public function __construct(
        protected EventDispatcherInterface $eventDispatcher
    ) {}

    public function fire(string $eventName, mixed ...$data): BaseRpcEvent
    {
        $event = RpcEvent::create($eventName, $data);
        $this->processEvent($event, $eventName);
        return $event;
    }

    protected function processEvent(BaseRpcEvent $event, string $eventName): void
    {
        $this->eventsPool[] = $event;
        $this->eventDispatcher->dispatch($event, $eventName);
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function addListener(string $event, callable $param, int $priority = 0): void
    {
        $this->eventDispatcher->addListener($event, $param, $priority);
    }

    public function fireRequest(RpcRequest $request): RpcRequestEvent
    {
        $event = new RpcRequestEvent($request);
        $this->processEvent($event, $event->getEventName());
        return $event;
    }

    public function fireAsyncRequest(RpcRequest $request): RpcAsyncRequestEvent
    {
        $event = new RpcAsyncRequestEvent($request);
        $this->processEvent($event, $event->getEventName());
        return $event;
    }

    public function fireError(RpcRequest $request, \Throwable $throwable): RpcErrorEvent
    {
        $event = new RpcErrorEvent($request, $throwable);
        $this->processEvent($event, $event->getEventName());
        return $event;
    }
}