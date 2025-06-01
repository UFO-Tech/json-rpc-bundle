<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Exceptions\BadParamException;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPostExecuteEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreExecuteEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreResponseEvent;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RPC\Param;
use Ufo\RpcObject\RpcResponse;

use function class_exists;
use function count;
use function interface_exists;
use function is_array;

#[AsEventListener(RpcEvent::PRE_EXECUTE, method: 'arrayToDTOTransform', priority: 1000)]
#[AsEventListener(RpcEvent::PRE_RESPONSE, method: 'objectToScalar', priority: 1000)]
class ParamsConverterEventListener
{

    public function __construct(
        protected ChainParamConvertor $paramConvertor
    ) {}

    public function objectToScalar(RpcPreResponseEvent $event): void
    {
        $result = $event->rpcRequest->getResponseObject()->getResult(true);
        $service = $event->service;
        if (!$result || !$service) return;
        if (!$responseInfo = $service->getResponseInfo()) return;

        $replacementParams = [];
        foreach ($responseInfo->getFormat() as $paramName => $type) {
            if ($paramName === '$dto') continue;
            if (!$realFormatFQCN = $responseInfo->getRealFormat($paramName)) continue;
            try {
                $replacementParams[$paramName] = $this->paramConvertor->toScalar(
                    $result->{$paramName}, ['classFQCN' => $realFormatFQCN]
                );
            } catch (BadParamException $e) {}
        }
        if (count($replacementParams) > 0) {
            $result = [
                ...$event->rpcRequest->getResponseObject()->getResult(),
                ...$replacementParams
            ];
            $event->rpcRequest->setResponse(new RpcResponse(
                $event->rpcRequest->getResponseObject()->getId(),
                $result
            ));
        }
    }

    public function arrayToDTOTransform(RpcPreExecuteEvent $event): void
    {
        $service = $event->service;
        $this->convertParams($service, $event->params);
        if (count($service->getParamsDto()) > 0) {
            foreach ($service->getParamsDto() as $paramName => $paramDto) {
                $dtoClass = $paramDto->dto->dtoFQCN;
                $dto = DTOTransformer::fromArray($dtoClass, $event->params[$paramName] ?? []);
                $event->params[$paramName] = $dto;
            }
        }
    }

    protected function convertParams(Service $service, array &$params): void
    {
        foreach ($service->getParams() as $paramName => $paramDefinition) {
            if ($paramAttribute = $paramDefinition->getAttributesCollection()->getAttribute(Param::class)) {

                $params[$paramName] = $this->paramConvertor->toObject(
                    $params[$paramName],
                    [
                        'param' => $paramAttribute,
                        'classFQCN' => $this->resolveParamTypeClassFQCN($paramDefinition->getRealType()),
                    ]
                );
            }
        }
    }

    protected function resolveParamTypeClassFQCN(array|string $type): string
    {
        if (is_array($type)) {
            foreach ($type as $typeItem) {
                try {
                    $type = $this->resolveParamTypeClassFQCN($typeItem);
                    break;
                } catch (BadParamException) {}
            }
        }

        if (!class_exists($type) && !interface_exists($type)) {
            throw new BadParamException('None of the parameter types is a valid class');
        }

        return $type;
    }
}
