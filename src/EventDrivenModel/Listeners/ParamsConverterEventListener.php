<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use ReflectionClass;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Throwable;
use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Exceptions\BadParamException;
use Ufo\DTO\Helpers\EnumResolver;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\DTO\Tests\TypeHintResolverTest;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPostExecuteEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreExecuteEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreResponseEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\DtoReflector;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcError\ConstraintsImposedException;
use Ufo\RpcObject\RPC\DTO;
use Ufo\RpcObject\RPC\Param;
use Ufo\RpcObject\RpcResponse;

use function array_map;
use function array_unique;
use function class_exists;
use function count;
use function interface_exists;
use function is_array;
use function is_object;
use function str_starts_with;

#[AsEventListener(RpcEvent::PRE_EXECUTE, method: 'dataToDTOTransform', priority: 1000)]
#[AsEventListener(RpcEvent::PRE_RESPONSE, method: 'objectToScalar', priority: 1000)]
class ParamsConverterEventListener
{

    public function __construct(
        protected ChainParamConvertor $paramConvertor,
        protected RpcEventFactory $eventFactory,
    ) {}

    public function objectToScalar(RpcPreResponseEvent $event): void
    {
        $result = $event->rpcRequest->getResponseObject()->getResult(true);
        $service = $event->service;
        if (!$result || !$service || !is_object($result)) return;
        if (!$responseInfo = $service->getResponseInfo()) {
            $responseInfo = new DTO($result::class);
            new DtoReflector($responseInfo, $this->paramConvertor);
        }

        $replacementParams = [];
        foreach ($responseInfo->getFormat() as $paramName => $type) {
            if (str_starts_with($paramName, '$')) continue;
            try {
                if (!$realFormatFQCN = $responseInfo->getRealFormat($paramName)) continue;

            } catch (\Throwable $e) {
                continue;
            }
            try {
                $replacementParams[$paramName] = $this->paramConvertor->toScalar(
                    $result->{$paramName}, [TypeHintResolver::CLASS_FQCN => $realFormatFQCN]
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

    public function dataToDTOTransform(RpcPreExecuteEvent $event): void
    {
        $service = $event->service;
        $this->convertParams($service, $event->params);
        if (count($service->getParamsDto()) > 0) {
            foreach ($service->getParamsDto() as $paramName => $paramDtoCollection) {
                $result = $event->params[$paramName] ?? null;
                $errors = [];

                foreach ($paramDtoCollection as $paramDto) {
                    if (!is_array($event->params[$paramName])) continue;
                    $dtoClass = $paramDto->dto->dtoFQCN;

                    if (!$paramDto->dto->isCollection()) {
                        try {
                            $result = $this->transformSingleObject($dtoClass, $event->params[$paramName] ?? []);
                            unset($errors[$paramName]);
                            break;
                        } catch (Throwable $exception) {
                            $errors[$paramName][] = $exception->getMessage();
                            $errors[$paramName] = array_unique($errors[$paramName]);
                            continue;
                        }
                    }

                    foreach ($event->params[$paramName] ?? [] as $key => $item) {
                        if (is_object($result[$key] ?? null)) {
                            unset($errors[$key]);
                            continue;
                        }

                        try {
                            $result[$key] = $this->transformSingleObject($dtoClass, $item);
                            unset($errors[$paramName][$key]);
                        } catch (Throwable $exception) {
                            $errors[$paramName][$key][] = $exception->getMessage();
                            $errors[$paramName][$key] = array_unique($errors[$paramName][$key]);
                        }
                    }

                }

                $event->params[$paramName] = $result;
                if (count($errors) > 0 ){
                    $e = new ConstraintsImposedException(
                        "Invalid Data for call method: {$service->getMethodName()}",
                        $errors
                    );
                    $this->eventFactory->fireError($event->rpcRequest, $e);
                }
            }

        }
    }

    protected function transformSingleObject(string $dtoClass, mixed $data): object
    {
        if (!is_array($data)) {
            throw new BadParamException('Invalid Data for create object: "' . $dtoClass . '". Data must be an array');
        }
        return DTOTransformer::fromArray($dtoClass, $data);

    }



    protected function convertParams(Service $service, array &$params): void
    {
        foreach ($service->getParams() as $paramName => $paramDefinition) {
            /** @var Param $paramAttribute */
            if ($paramAttribute = $paramDefinition->getAttributesCollection()->getAttribute(Param::class)) {

                $process = function ($value) use ($paramAttribute, $paramDefinition, $service) {
                    if (!is_scalar($value) && !is_null($value)) {
                        return $value;
                    }

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
                            TypeHintResolver::CLASS_FQCN => $classFQCN ?? $this->resolveParamTypeClassFQCN($paramDefinition->getRealType()),
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
