<?php

namespace Ufo\JsonRpcBundle\Exceptions;

abstract class AbstractJsonRpcBundleException extends \Exception
{
    const DEFAULT_CODE = -32603;
    protected $code = self::DEFAULT_CODE;

    public static function fromThrowable(\Throwable $e)
    {
        return new static(
            $e->getMessage(),
            $e->getCode(),
            $e
        );
    }
}
