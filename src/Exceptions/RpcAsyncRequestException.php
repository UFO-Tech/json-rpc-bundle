<?php

namespace Ufo\JsonRpcBundle\Exceptions;

class RpcAsyncRequestException extends AbstractJsonRpcBundleException implements IUserInputExceptionInterface
{
    protected $message = 'Async request is invalid';
    protected $code = -32300;
}
