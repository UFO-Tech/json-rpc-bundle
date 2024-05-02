<?php
namespace Ufo\JsonRpcBundle\Interfaces;



interface IRpcValidator
{
    public function validateMethodParams(
        object $procedureObject,
        string $procedureMethod,
        array $params
    ): void;
        
}