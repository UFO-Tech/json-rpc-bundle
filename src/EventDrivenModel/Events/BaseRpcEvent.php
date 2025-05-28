<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Events;

use Symfony\Contracts\EventDispatcher\Event;

class BaseRpcEvent extends Event
{
    const string NAME = 'rpc.noname';

    public function getEventName(): string
    {
        return static::NAME;
    }
}