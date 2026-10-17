<?php

namespace Ufo\JsonRpcBundle\Exceptions;

use Ufo\RpcError\RpcBadRequestException;

class BatchIsDisabledException extends RpcBadRequestException
{
    protected $message = 'Batch requests are disabled';
}
