<?php

namespace Ufo\JsonRpcBundle\Exceptions;


use Ufo\RpcError\RpcBadParamException;

class ConstraintsImposedException extends RpcBadParamException
{
    public function __construct(protected  $message, protected array $constraintsImposed)
    {
        parent::__construct($message);
    }

    /**
     * @return array
     */
    public function getConstraintsImposed(): array
    {
        return $this->constraintsImposed;
    }
}
