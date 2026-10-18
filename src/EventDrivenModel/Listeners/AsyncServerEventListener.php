<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcAsyncRequestEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPostResponseEvent;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;
use Ufo\JsonRpcBundle\Server\RequestPrepare\IRpcHttpCarrier;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\JsonRpcBundle\Server\Async\RpcCallbackProcessor;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcError\WrongWayException;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcAsyncOutputEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\RpcObject\RPC\Cache;
use Ufo\RpcObject\RpcAsyncRequest;
use Ufo\RpcObject\RpcError;
use Ufo\RpcObject\RpcNotificationRequest;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Transformer\RpcResponseContextBuilder;

use function array_key_exists;
use function is_array;
use function json_decode;


#[AsEventListener(RpcEvent::OUTPUT_ASYNC, 'process', priority: 10)]
#[AsEventListener(RpcEvent::POST_RESPONSE, 'processAsync', priority: 10)]
class AsyncServerEventListener
{

    public function __construct(
        protected RpcResponseContextBuilder $contextBuilder,
        protected SerializerInterface $serializer,
        protected RpcAsyncProcessor $asyncProcessor,
        protected ?RequestCarrier $requestCarrier = null,
        protected ?RpcMainConfig $rpcConfig = null,
    ) {}

    public function processAsync(RpcPostResponseEvent $event): void
    {
        $request = $event->rpcRequest;
        if (!$request->isAsync() && !$request instanceof RpcNotificationRequest) {
            return;
        }
        if ($this->shouldDetach() && $this->asyncProcessor->detach($request)) {
            return;
        }
        $this->asyncProcessor->processAsync($request);
    }

    /**
     * notification.process: only for a single HTTP request, the case that keeps the HTTP worker busy after
     * the response. Batch items run the way the batch mode says; in CLI (a child or detached process,
     * a consumer) it always runs inline, so a detached process never detaches itself again.
     */
    protected function shouldDetach(): bool
    {
        if (!$this->rpcConfig?->notificationConfig->process) {
            return false;
        }
        $carrier = $this->requestCarrier?->getCarrier();
        if (!$carrier instanceof IRpcHttpCarrier) {
            return false;
        }
        try {
            $carrier->getBatchRequestObject();
            return false;
        } catch (WrongWayException) {
            return true;
        }
    }

    public function process(RpcAsyncOutputEvent $event): void
    {
        // A notification has already been executed by its child process and gets no response object.
        if ($event->rpcRequest instanceof RpcNotificationRequest) {
            $event->stopPropagation();
            return;
        }
        $batchRequest = $event->batchRequest;
        $output = $event->output;
        try {
            if (empty($output)) {
                throw new RpcAsyncRequestException('The async process did not return any results. Try increasing the timeout by adding the "$rpc.timeout" parameter on params request');
            }
            $response = $this->responseFromOutput($output);
        } catch (RpcAsyncRequestException $e) {
            $error = AbstractRpcErrorException::fromThrowable($e, false);
            $response = $this->responseFromError($error, $event);
        } catch (Throwable $e) {
            if ($e instanceof AbstractRpcErrorException) {
                $error = AbstractRpcErrorException::fromThrowable($e);
            } else {
                $error = AbstractRpcErrorException::fromCode(AbstractRpcErrorException::DEFAULT_CODE, 'Uncatched async error', $e);
            }
            $response = $this->responseFromError($error, $event);
        }
        $result = $this->serializer->normalize(
            $response,
            context:  $this->contextBuilder->removeParent()->withResponseSignature($response)->toArray()
        );

        $batchRequest->addResponse($response, $result);
        $event->stopPropagation();
    }

    /**
     * A successful result is taken exactly as the child process returned it: denormalizing the
     * union-typed RpcResponse::$result turns JSON integers into floats (5 -> 5.0), which then breaks
     * @FROM substitution of that result into an int parameter of another batch item.
     */
    protected function responseFromOutput(string $output): RpcResponse
    {
        $decoded = json_decode($output, true);
        if (is_array($decoded) && array_key_exists(RpcResponse::IS_RESULT, $decoded) && isset($decoded['id'])) {
            return new RpcResponse(
                $decoded['id'],
                $decoded[RpcResponse::IS_RESULT],
                version: $decoded['jsonrpc'] ?? RpcRequest::DEFAULT_VERSION,
            );
        }

        return $this->serializer->deserialize($output, RpcResponse::class, 'json');
    }

    protected function responseFromError(Throwable $error, RpcAsyncOutputEvent $event): RpcResponse
    {
        return new RpcResponse(
            id: $event->rpcRequest->getId() ?? 'not_processed',
            error: RpcError::fromThrowable($error),
            version: $event->rpcRequest->getVersion(),
            requestObject: $event->rpcRequest,
            contextBuilder: $this->contextBuilder
        );
    }
}
