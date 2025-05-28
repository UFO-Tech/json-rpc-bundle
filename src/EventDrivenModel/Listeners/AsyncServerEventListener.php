<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcAsyncOutputEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\RpcObject\RPC\Cache;
use Ufo\RpcObject\RpcError;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Transformer\RpcResponseContextBuilder;


#[AsEventListener(RpcEvent::OUTPUT_ASYNC, 'process', priority: 10)]
class AsyncServerEventListener
{

    public function __construct(
        protected RpcResponseContextBuilder $contextBuilder,
        protected SerializerInterface $serializer
    ) {}

    public function process(RpcAsyncOutputEvent $event): void
    {
        $batchRequest = $event->batchRequest;
        $output = $event->output;
        try {
            if (empty($output)) {
                throw new RpcAsyncRequestException('The async process did not return any results. Try increasing the timeout by adding the "$rpc.timeout" parameter on params request');
            }
            /**
             * @var RpcResponse $response
             */
            $response = $this->serializer->deserialize($output, RpcResponse::class, 'json');
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

    protected function responseFromError(\Throwable $error, RpcAsyncOutputEvent $event): RpcResponse
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
