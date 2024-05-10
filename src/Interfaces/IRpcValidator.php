<?php

namespace Ufo\JsonRpcBundle\Interfaces;

use Ufo\JsonRpcBundle\Exceptions\ConstraintsImposedException;

interface IRpcValidator
{
    /**
     * @throws ConstraintsImposedException
     */
    public function validateMethodParams(
        object $procedureObject,
        string $procedureMethod,
        array $params
    ): void;

}