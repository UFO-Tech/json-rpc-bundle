<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Server\RequestPrepare;

use Symfony\Component\HttpFoundation\Request;

/**
 * A carrier of an RPC request that came in an HTTP request (core route, rest-bundle, mcp-bundle).
 * Ties the carrier to its HTTP request for kernel.terminate (POST_RESPONSE) and lets
 * notification.process detach a single HTTP request.
 */
interface IRpcHttpCarrier extends IRpcRequestCarrier
{
    public function getHttpRequest(): Request;
}
