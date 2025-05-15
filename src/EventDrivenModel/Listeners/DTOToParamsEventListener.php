<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Ufo\DTO\DTOTransformer;
use Ufo\RpcObject\Events\RpcEvent;
use Ufo\RpcObject\Events\RpcPreExecuteEvent;

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
