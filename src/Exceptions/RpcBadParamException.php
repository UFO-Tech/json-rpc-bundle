<?php

namespace Ufo\JsonRpcBundle\Exceptions;

class RpcBadParamException extends RpcBadRequestException implements IUserInputExceptionInterface
{
    protected $message = 'Required parameter not passed';
    protected $code = -32602;
}
