<?php

namespace Ufo\JsonRpcBundle\Exceptions;

class RpcMethodNotFoundExceptionRpc extends RpcBadRequestException implements IUserInputExceptionInterface
{
    protected $message = 'Method not found';
    protected $code = -32601;
}
