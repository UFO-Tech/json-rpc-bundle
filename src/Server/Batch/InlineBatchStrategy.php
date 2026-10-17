<?php

namespace Ufo\JsonRpcBundle\Server\Batch;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Throwable;
use Ufo\JsonRpcBundle\ConfigService\RpcBatchConfig;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreResponseEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcNotificationRequest;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Transformer\RpcResponseContextBuilder;

use function array_key_first;
use function ob_end_clean;
use function ob_start;

/**
 * batch.process: false — items run one by one in the current process, each the way a child process
 * runs it: its own event pool, so its POST_RESPONSE (where notifications and $rpc.callback are executed)
 * fires exactly once, and an error becomes the item's error response.
 */
class InlineBatchStrategy implements IBatchStrategy
{
    public function __construct(
        protected RpcEventFactory $eventFactory,
        protected NormalizerInterface $serializer,
        protected RpcResponseContextBuilder $contextBuilder,
        protected RpcAsyncProcessor $asyncProcessor,
    ) {}

    public function supports(RpcBatchConfig $config): bool
    {
        return !$config->process;
    }

    public function handle(RpcBatchRequest $batchRequest, RpcRequestHandler $handler): void
    {
        $queue = &$batchRequest->getReadyToHandle();
        while ($queue) {
            $key = array_key_first($queue);
            $singleRequest = $queue[$key];
            unset($queue[$key]);

            if ($this->asyncProcessor->detachBatchNotification($singleRequest)) {
                continue;
            }
            $response = $this->execute($singleRequest, $handler);
            if ($singleRequest instanceof RpcNotificationRequest) {
                continue;
            }
            $batchRequest->addResponse($response, $this->serializer->normalize(
                $response,
                context: $this->contextBuilder->removeParent()->withResponseSignature($response)->toArray()
            ));
        }
        // Nothing of the items may be fired again on kernel.terminate of the batch request.
        $this->eventFactory->reset();
    }

    protected function execute(RpcRequest $singleRequest, RpcRequestHandler $handler): RpcResponse
    {
        $this->eventFactory->reset();
        try {
            $response = $handler->provideSingleRequestToResponse($singleRequest);
        } catch (Throwable $e) {
            $this->eventFactory->fireError($singleRequest, $e);
            $response = $singleRequest->getResponseObject() ?? throw $e;
        }

        // What console.terminate does in a child process. Output is swallowed: whatever the executed
        // method prints must not get into the HTTP response.
        ob_start();
        try {
            /** @var RpcPreResponseEvent $preResponse */
            $preResponse = $this->eventFactory->getEvent(RpcEvent::getEventFQSN(RpcEvent::PRE_RESPONSE));
            $this->eventFactory->fire(
                RpcEvent::POST_RESPONSE,
                $preResponse->response,
                $preResponse->rpcRequest,
                $preResponse->service,
            );
        } catch (Throwable) {
        } finally {
            ob_end_clean();
        }

        return $response;
    }
}
