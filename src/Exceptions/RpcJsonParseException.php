<?php

namespace Ufo\JsonRpcBundle\Exceptions;

class RpcJsonParseException extends AbstractJsonRpcBundleException implements IUserInputExceptionInterface
{
    protected $code = -32700;
}
