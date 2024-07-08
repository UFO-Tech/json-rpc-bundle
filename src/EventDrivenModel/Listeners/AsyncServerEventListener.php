<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use TypeError;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Serializer\RpcResponseContextBuilder;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\RpcObject\Events\RpcAsyncOutputEvent;
use Ufo\RpcObject\Events\RpcEvent;
use Ufo\RpcObject\Events\RpcPreExecuteEvent;
use Ufo\RpcObject\RpcError;
use Ufo\RpcObject\RpcResponse;

use function call_user_func_array;
use function preg_replace;

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
        $request = $event->rpcRequest;
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
//            $response = new RpcResponse(
//                id: $request->getId(),
//                error: $error,
//                version: $request->getVersion(),
//                requestObject: $request,
//                contextBuilder: $this->contextBuilder
//            );
        }
        $result = $this->serializer->normalize($response, context:  $this->contextBuilder->withResponseSignature($response)
                                                                    ->toArray());
        $batchRequest->addResult($result);



//        $event->stopPropagation();
    }

}
