<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\EventDrivenModel;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreResponseEvent;
use WeakMap;

use function spl_object_id;

/**
 * RpcEvent::POST_RESPONSE for every executed request, whatever carrier it came with and however many
 * requests one HTTP request or command executes. Requests are deferred here and fired on
 * kernel.terminate / console.terminate (SymfonyFlowListener); inline batch items fire at once.
 *
 * A request is due a POST_RESPONSE by its last PRE_RESPONSE event: only a successful response of a method
 * (with a service). An error response gets none, a cached response has no PRE_RESPONSE at all.
 */
class PostResponseQueue implements ResetInterface
{
    /** @var array<array{RpcPreResponseEvent, ?Request}> */
    protected array $pending = [];

    /** @var WeakMap<RpcPreResponseEvent, true> already taken from the pool: an event fires once */
    protected WeakMap $taken;

    public function __construct(
        protected RpcEventFactory $eventFactory,
        protected LoggerInterface $logger = new NullLogger(),
    ) {
        $this->taken = new WeakMap();
    }

    /**
     * PRE_RESPONSE events of the current event pool that are due a POST_RESPONSE.
     *
     * @return RpcPreResponseEvent[]
     */
    public function takeFromPool(): array
    {
        $last = [];
        foreach ($this->eventFactory->getEvents(RpcPreResponseEvent::class) as $event) {
            $last[spl_object_id($event->rpcRequest)] = $event;
        }
        $due = [];
        foreach ($last as $event) {
            if (isset($this->taken[$event])) continue;
            $this->taken[$event] = true;
            if ($event->service === null || $event->response->getError() !== null) continue;
            $due[] = $event;
        }
        return $due;
    }

    /**
     * @param RpcPreResponseEvent[] $events
     * @param Request|null $boundTo the HTTP request whose kernel.terminate fires them; null — console.terminate
     */
    public function defer(array $events, ?Request $boundTo = null): void
    {
        foreach ($events as $event) {
            $this->pending[] = [$event, $boundTo];
        }
    }

    public function hasPending(?Request $for = null): bool
    {
        foreach ($this->pending as [, $boundTo]) {
            if ($boundTo === $for) return true;
        }
        return false;
    }

    public function flush(?Request $for = null): void
    {
        $due = [];
        foreach ($this->pending as $key => [$event, $boundTo]) {
            if ($boundTo !== $for) continue;
            $due[] = $event;
            unset($this->pending[$key]);
        }
        $this->fire($due);
    }

    /**
     * A failing listener neither stops the other requests nor reaches the client: the response is already sent.
     *
     * @param RpcPreResponseEvent[] $events
     */
    public function fire(array $events): void
    {
        foreach ($events as $event) {
            try {
                $this->eventFactory->fire(RpcEvent::POST_RESPONSE, $event->response, $event->rpcRequest, $event->service);
            } catch (Throwable $e) {
                $this->logger->error('RPC post-response listener failed.', [
                    'exception' => $e,
                    'method' => $event->rpcRequest->getMethod(),
                ]);
            }
        }
    }

    public function reset(): void
    {
        $this->pending = [];
        $this->taken = new WeakMap();
    }
}
