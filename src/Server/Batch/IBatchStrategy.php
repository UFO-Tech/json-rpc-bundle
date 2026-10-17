<?php

namespace Ufo\JsonRpcBundle\Server\Batch;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Ufo\JsonRpcBundle\ConfigService\RpcBatchConfig;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\RpcObject\RpcBatchRequest;

/**
 * How the items of a batch request are executed (batch.process).
 */
#[AutoconfigureTag(IBatchStrategy::TAG)]
interface IBatchStrategy
{
    const string TAG = 'rpc.batch_strategy';

    /**
     * @param RpcBatchConfig $config
     */
    public function supports(RpcBatchConfig $config): bool;

    /**
     * Executes every ready item of the batch, including the ones that become ready when the items
     * they depend on (@FROM) get their responses, and adds the responses to the batch.
     * JSON-RPC 2.0: a notification is executed, but gets no response object.
     */
    public function handle(RpcBatchRequest $batchRequest, RpcRequestHandler $handler): void;
}
