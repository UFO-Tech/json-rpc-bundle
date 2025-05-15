<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcObject\Events\RpcAsyncOutputEvent;
use Ufo\RpcObject\Events\RpcEvent;
use Ufo\RpcObject\RPC\Cache;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Transformer\RpcResponseContextBuilder;


#[AsEventListener(RpcEvent::OUTPUT_ASYNC, 'process', priority: 10)]
class AsyncServerEventListener
{

    public function __construct(
        #[Autowire('kernel.environment')]
        protected string $environment,
        protected RpcEventFactory $eventFactory,
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
        } catch (Throwable $e) {
            if ($e instanceof AbstractRpcErrorException) {
                $error =  AbstractRpcErrorException::fromThrowable($e);
            } else {
                $error = AbstractRpcErrorException::fromCode(AbstractRpcErrorException::DEFAULT_CODE, 'Uncatched async error', $e);
            }
            throw $error;
        }
        $result = $this->serializer->normalize(
            $response,
            context:  $this->contextBuilder->withResponseSignature($response)->toArray()
        );

        $response = new RpcResponse(
            id: $event->rpcRequest->getId() ?? 'not_processed',
            result: $result,
            version: $event->rpcRequest->getVersion(),
            requestObject: $event->rpcRequest,
            cache: $service?->getAttrCollection()->getAttribute(Cache::class),
            contextBuilder: $this->contextBuilder
        );
        $event->rpcRequest->setResponse($response);
        $batchRequest->addResult($result);
        $event->stopPropagation();
    }

}
