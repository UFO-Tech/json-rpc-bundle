<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Events;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

class RpcEvent extends BaseRpcEvent
{
    const string NAME = 'rpc.custom_event';

    const string REQUEST = 'rpc.request';
    const string REQUEST_ASYNC = 'rpc.async_request';
    const string OUTPUT_ASYNC = 'rpc.async_output';
    const string PRE_EXECUTE = 'rpc.pre_execute';
    const string POST_EXECUTE = 'rpc.post_execute';
    const string PRE_RESPONSE = 'rpc.pre_response';
    const string POST_RESPONSE = 'rpc.post_response';
    const string ERROR = 'rpc.error';

    public function __construct(public array $eventData) {}

    /**
     * @throws ReflectionException
     */
    public static function create(string $eventName, array $data): BaseRpcEvent
    {
        try {
            $eventClass = static::getEventFQSN($eventName);
            $reflectionClass = new ReflectionClass($eventClass);
            $event = $reflectionClass->newInstanceArgs($data);
        } catch (InvalidArgumentException) {
            $event = new static($data);
        }

        return $event;
    }

    public function getEventName(): string
    {
        return static::NAME;
    }

    public static function getEventFQSN($eventName): string
    {
        return match ($eventName) {
            static::REQUEST => RpcRequestEvent::class,
            static::REQUEST_ASYNC => RpcAsyncRequestEvent::class,
            static::OUTPUT_ASYNC => RpcAsyncOutputEvent::class,
            static::PRE_EXECUTE => RpcPreExecuteEvent::class,
            static::POST_EXECUTE => RpcPostExecuteEvent::class,
            static::PRE_RESPONSE => RpcPreResponseEvent::class,
            static::POST_RESPONSE => RpcPostResponseEvent::class,
            static::ERROR => RpcErrorEvent::class,
            default => throw new InvalidArgumentException()
        };
    }
}