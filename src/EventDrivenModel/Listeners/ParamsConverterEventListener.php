<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Exceptions\BadParamException;
use Ufo\DTO\Helpers\EnumResolver;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\DTO\Tests\TypeHintResolverTest;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPostExecuteEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreExecuteEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreResponseEvent;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RPC\Param;
use Ufo\RpcObject\RpcResponse;

use function array_map;
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
        if (!$result || !$service || !$responseInfo = $service->getResponseInfo()) return;

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
            foreach ($service->getParamsDto() as $paramName => $paramDtoCollection) {
                $result = $event->params[$paramName] ?? null;

                foreach ($paramDtoCollection as $paramDto) {
                    try {
                        if (!is_array($event->params[$paramName])) continue;
                        $dtoClass = $paramDto->dto->dtoFQCN;

                        if (!$paramDto->dto->isCollection()) {
                            $result = DTOTransformer::fromArray($dtoClass, $event->params[$paramName] ?? []);
                            break;
                        }

                        foreach ($event->params[$paramName] ?? [] as $key => $item) {
                            if (is_object($result[$key] ?? null)) continue;

                            try {
                                $result[$key] = DTOTransformer::fromArray($dtoClass, $item);
                            } catch (\Throwable) {
                                $result[$key] = $item;
                            }
                        }
                    } catch (\Throwable $exception) {
                        continue;
                    }

                }

                $event->params[$paramName] = $result;
            }
        }
    }

    protected function convertParams(Service $service, array &$params): void
    {
        foreach ($service->getParams() as $paramName => $paramDefinition) {
            /** @var Param $paramAttribute */
            if ($paramAttribute = $paramDefinition->getAttributesCollection()->getAttribute(Param::class)) {

                $process = function ($value) use ($paramAttribute, $paramDefinition, $service) {
                    $classFQCN = null;

                    TypeHintResolver::filterSchema(
                        $paramDefinition->getType(),
                        function (array $schemaItem) use ($paramDefinition, &$classFQCN, $service) {
                            if (
                                $classFQCN
                                || !($name = $schemaItem[EnumResolver::ENUM][EnumResolver::ENUM_NAME] ?? false)
                            ) return;

                            $classFQCN = EnumResolver::getEnumFQCN(
                                TypeHintResolver::typeWithNamespaceOrDefault(
                                    $name, $service->uses
                                ) ?? ''
                            );
                        }
                    );


                    return $this->paramConvertor->toObject(
                        $value,
                        [
                            'param' => $paramAttribute,
                            'classFQCN' => $classFQCN ?? $this->resolveParamTypeClassFQCN($paramDefinition->getRealType()),
                        ]
                    );
                };

                $params[$paramName] = match (true) {
                    $paramAttribute->isCollection() => array_map($process, $params[$paramName]),
                    default => $process($params[$paramName]),
                };
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
