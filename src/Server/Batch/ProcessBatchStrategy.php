<?php

namespace Ufo\JsonRpcBundle\Server\Batch;

use Closure;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\JsonRpcBundle\ConfigService\RpcBatchConfig;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcAsyncOutputEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcError\RpcTokenNotSentException;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcRequest;

use function strtolower;

/**
 * batch.process: true — every item runs in its own `ufo:rpc:process` child process, at most
 * batch.process_count at a time. A child's output is turned into the item's response by the
 * RpcEvent::OUTPUT_ASYNC listener (AsyncServerEventListener).
 */
class ProcessBatchStrategy implements IBatchStrategy
{
    public function __construct(
        protected RpcAsyncProcessor $asyncProcessor,
        protected IRpcSecurity $rpcSecurity,
        protected SerializerInterface $serializer,
        protected RpcEventFactory $eventFactory,
    ) {}

    public function supports(RpcBatchConfig $config): bool
    {
        return $config->process;
    }

    /**
     * @throws RpcAsyncRequestException
     */
    public function handle(RpcBatchRequest $batchRequest, RpcRequestHandler $handler): void
    {
        $this->processQueue($batchRequest, $batchRequest->getReadyToHandle());
    }

    /**
     * @throws RpcAsyncRequestException
     */
    protected function processQueue(RpcBatchRequest $batchRequest, array &$queue): void
    {
        // The token is optional here: a protected API without a token is rejected before the batch
        // is handled, and an unprotected API may be called without one (children do not check it then).
        try {
            $tokenHolder = $this->rpcSecurity->getTokenHolder();
            $tokenName = strtolower($tokenHolder->getTokenKey());
            $token = $tokenHolder->getToken();
        } catch (RpcTokenNotSentException) {
            $tokenName = $token = null;
        }
        foreach ($queue as $key => &$singleRequest) {
            /**
             * @var RpcRequest $singleRequest
             */
            if ($this->asyncProcessor->detachBatchNotification($singleRequest)) {
                unset($queue[$key]);
                continue;
            }
            $singleRequest->refreshRawJson($this->serializer);
            $this->asyncProcessor->createProcesses(
                $singleRequest,
                tokenName: $tokenName,
                token: $token,
                timeout: $singleRequest->getRpcParams()->getTimeout()
            );
            unset($queue[$key]);
        }
        $this->asyncProcessor->process($this->onChildOutput($batchRequest));
    }

    protected function onChildOutput(RpcBatchRequest $batchRequest): Closure
    {
        return function (string $output, RpcRequest $request) use ($batchRequest) {
            /** @var RpcAsyncOutputEvent $event */
            $event = $this->eventFactory->fire(RpcEvent::OUTPUT_ASYNC, $request, $batchRequest, $output);
            if ($event->batchRequest->getReadyToHandle()) {
                $this->processQueue($event->batchRequest, $event->batchRequest->getReadyToHandle());
            }
        };
    }
}
