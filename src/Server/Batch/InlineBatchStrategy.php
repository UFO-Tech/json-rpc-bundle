<?php

namespace Ufo\JsonRpcBundle\Server\Batch;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Ufo\JsonRpcBundle\ConfigService\RpcBatchConfig;
use Ufo\JsonRpcBundle\EventDrivenModel\PostResponseQueue;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\JsonRpcBundle\Server\RpcExecutor;
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
        protected ?RpcExecutor $executor = null,
        protected ?PostResponseQueue $postResponseQueue = null,
    ) {
        $this->postResponseQueue ??= new PostResponseQueue($this->eventFactory);
    }

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
        // Created without the container: run() uses neither the carrier nor the request stack.
        $this->executor ??= new RpcExecutor($handler, new RequestCarrier(), $this->eventFactory, $this->postResponseQueue);
        $response = $this->executor->run($singleRequest);

        // What console.terminate does in a child process. Output is swallowed: whatever the executed
        // method prints must not get into the HTTP response.
        ob_start();
        try {
            $this->postResponseQueue->fire($this->postResponseQueue->takeFromPool());
        } finally {
            ob_end_clean();
        }

        return $response;
    }
}
