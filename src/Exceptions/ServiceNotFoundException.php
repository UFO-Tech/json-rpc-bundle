<?php

namespace Ufo\JsonRpcBundle\Exceptions;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class ServiceNotFoundException extends Exception implements NotFoundExceptionInterface
{

}
