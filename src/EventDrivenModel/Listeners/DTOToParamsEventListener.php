<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Throwable;
use TypeError;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Locker\LockerService;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceLocator;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\RpcObject\DTO\DTOTransformer;
use Ufo\RpcObject\Events\RpcErrorEvent;
use Ufo\RpcObject\Events\RpcEvent;
use Ufo\RpcObject\Events\RpcPreExecuteEvent;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Transformer\RpcResponseContextBuilder;

use function call_user_func_array;
use function count;
use function preg_replace;

#[AsEventListener(RpcEvent::PRE_EXECUTE, 'process', priority: 1000)]
class DTOToParamsEventListener
{
    public function process(RpcPreExecuteEvent $event): void
    {
        $service = $event->service;

        if (count($service->getParamsDto()) > 0) {
            foreach ($service->getParamsDto() as $paramName => $paramDto) {
                $dtoClass = $paramDto->dto->dtoFQCN;
                $dto = DTOTransformer::fromArray($dtoClass, $event->params[$paramName] ?? []);
                $event->params[$paramName] = $dto;
            }
        }
    }

}
