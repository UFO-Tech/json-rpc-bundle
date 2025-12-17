<?php

namespace Ufo\JsonRpcBundle\Exceptions;

use Exception;

class EventCreateException extends Exception
{
    protected $message = 'Event creation failed';

}