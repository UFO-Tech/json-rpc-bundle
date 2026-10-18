<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Server;

use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;
use Ufo\JsonRpcBundle\EventDrivenModel\PostResponseQueue;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Server\RequestPrepare\IRpcRequestCarrier;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

/**
 * Executes one RPC request outside the core HTTP route (rest-bundle, mcp-bundle) the way the core does.
 * Returns a response, does not throw: an exception that escapes the handler goes through "rpc.error",
 * so the error response and its logging are the core ones. POST_RESPONSE is deferred to
 * kernel.terminate / console.terminate (PostResponseQueue), once per executed request.
 * The token is not checked here: every adapter checks it its own way.
 */
class RpcExecutor
{
    public function __construct(
        protected RpcRequestHandler $requestHandler,
        protected RequestCarrier $requestCarrier,
        protected RpcEventFactory $eventFactory,
        protected PostResponseQueue $postResponseQueue,
        protected ?RequestStack $requestStack = null,
    ) {}

    /**
     * @throws WrongWayException a carrier without a single request (batch)
     */
    public function execute(IRpcRequestCarrier $carrier): RpcResponse
    {
        $this->requestCarrier->resetParams();
        $this->requestCarrier->setCarrier($carrier);

        return $this->executeRequest($carrier->getRequestObject());
    }

    /**
     * The request under the carrier already set in RequestCarrier.
     */
    public function executeRequest(RpcRequest $request): RpcResponse
    {
        $response = $this->run($request);
        $this->postResponseQueue->defer(
            $this->postResponseQueue->takeFromPool(),
            $this->requestStack?->getMainRequest()
        );

        return $response;
    }

    /**
     * Executes the request with its own event pool; POST_RESPONSE is left to the caller.
     */
    public function run(RpcRequest $request): RpcResponse
    {
        $this->eventFactory->reset();
        try {
            return $this->requestHandler->provideSingleRequestToResponse($request);
        } catch (Throwable $e) {
            // As RpcServerListener does: the error data is normalized by environment only for RPC exceptions.
            $this->eventFactory->fireError(
                $request,
                $e instanceof AbstractRpcErrorException ? $e : RpcRuntimeException::fromThrowable($e)
            );
            return $request->getResponseObject() ?? throw $e;
        }
    }
}
