<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel;

use Symfony\Component\HttpClient\Exception\EventSourceException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\BaseRpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcAsyncRequestEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcErrorEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcRequestEvent;
use Ufo\RpcObject\RpcRequest;

use function array_filter;
use function current;
use function is_string;

class RpcEventFactory
{
    /**
     * @var RpcEvent[]
     */
    protected array $eventsPool = [];

    public function __construct(
        protected EventDispatcherInterface $eventDispatcher
    ) {}

    public function fire(string|BaseRpcEvent $event, mixed ...$data): BaseRpcEvent
    {
        if (is_string($event)) {
            $event = RpcEvent::create($event, $data);
        }
        $this->processEvent($event, $event->getEventName());
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

    public function getEvent(string $eventFQCN): BaseRpcEvent
    {
        $filteredEvents = array_filter($this->eventsPool, fn($event) => $event instanceof $eventFQCN);
        if (empty($filteredEvents))
            throw new EventSourceException('No event found for ' . $eventFQCN);
        return current($filteredEvents);
    }
}