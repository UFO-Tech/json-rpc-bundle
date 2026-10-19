<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Throwable;
use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Exceptions\BadParamException;
use Ufo\DTO\Helpers\EnumResolver;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreExecuteEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreResponseEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\Server\RpcCache\RpcMetadataCache;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\DtoReflector;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcError\ConstraintsImposedException;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcObject\RPC\DTO;
use Ufo\RpcObject\RPC\Param;
use Ufo\RpcObject\RpcResponse;

use function array_is_list;
use function array_key_exists;
use function array_unique;
use function class_exists;
use function count;
use function interface_exists;
use function is_array;
use function is_bool;
use function is_object;
use function str_starts_with;

#[AsEventListener(RpcEvent::PRE_EXECUTE, method: 'dataToDTOTransform', priority: 1000)]
#[AsEventListener(RpcEvent::PRE_RESPONSE, method: 'objectToScalar', priority: 1000)]
class ParamsConverterEventListener
{
    /**
     * Response formats for methods whose declared return type is not a concrete class
     * (object, mixed, interface, union), keyed by the actual result class. Building one with
     * DtoReflector reads and tokenizes the class file, so it is kept in memory for the process
     * and in the metadata cache across requests.
     *
     * @var array<class-string, DTO>
     */
    protected array $runtimeResponseInfo = [];

    public function __construct(
        protected ChainParamConvertor $paramConvertor,
        protected RpcEventFactory $eventFactory,
        protected ?RpcMetadataCache $metadataCache = null,
    ) {}

    public function objectToScalar(RpcPreResponseEvent $event): void
    {
        $result = $event->rpcRequest->getResponseObject()->getResult(true);
        $service = $event->service;
        if (!$result || !$service || !is_object($result)) return;
        $responseInfo = $service->getResponseInfo()
            ?? ($this->runtimeResponseInfo[$result::class] ??= $this->reflectResponseInfo($result::class));

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

    protected function reflectResponseInfo(string $resultClass): DTO
    {
        $build = fn() => $this->buildResponseInfo($resultClass);
        return $this->metadataCache?->get('response_info.'.$resultClass, DTO::class, $build) ?? $build();
    }

    protected function buildResponseInfo(string $resultClass): DTO
    {
        $responseInfo = new DTO($resultClass);
        new DtoReflector($responseInfo, $this->paramConvertor);
        return $responseInfo;
    }

    public function dataToDTOTransform(RpcPreExecuteEvent $event): void
    {
        $service = $event->service;
        $convertErrors = $this->convertParams($service, $event->params);
        if (count($convertErrors) > 0) {
            $this->eventFactory->fireError($event->rpcRequest, new ConstraintsImposedException(
                sprintf(ValidateParamsListener::INVALID_DATA_FOR_CALL_METHOD_TEMPLATE, $event->rpcRequest->getMethod()),
                $convertErrors
            ));
            $event->stopPropagation();
            return;
        }
        $dtoErrors = [];
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
                            $errors[$paramName][] = ValidateParamsListener::normalizeMessage($exception->getMessage());
                            $errors[$paramName] = array_unique($errors[$paramName]);
                            continue;
                        }
                    }

                    foreach ($event->params[$paramName] ?? [] as $key => $item) {
                        if (is_object($result[$key] ?? null)) {
                            unset($errors[$paramName][$key]);
                            continue;
                        }

                        try {
                            $result[$key] = $this->transformSingleObject($dtoClass, $item);
                            unset($errors[$paramName][$key]);
                        } catch (Throwable $exception) {
                            $errors[$paramName][$key][] = ValidateParamsListener::normalizeMessage($exception->getMessage());
                            $errors[$paramName][$key] = array_unique($errors[$paramName][$key]);
                        }
                    }

                }

                $event->params[$paramName] = $result;
                $dtoErrors += array_filter($errors, static fn (array $messages): bool => count($messages) > 0);
            }

        }
        if (count($dtoErrors) > 0) {
            $this->eventFactory->fireError($event->rpcRequest, new ConstraintsImposedException(
                sprintf(ValidateParamsListener::INVALID_DATA_FOR_CALL_METHOD_TEMPLATE, $event->rpcRequest->getMethod()),
                $dtoErrors
            ));
            $event->stopPropagation();
        }
    }

    protected function transformSingleObject(string $dtoClass, mixed $data): object
    {
        if (!is_array($data)) {
            throw new BadParamException('Invalid Data for create object: "' . $dtoClass . '". Data must be an array');
        }
        return DTOTransformer::fromArray($dtoClass, $data);

    }



    /**
     * @return array<string, array> messages of the values a convertor rejected, keyed by parameter name
     */
    protected function convertParams(Service $service, array &$params): array
    {
        $errors = [];
        // Positional params stay a list (unless the method has injected arguments): their key is the position.
        $positional = array_is_list($params);
        $position = 0;
        foreach ($service->getParams() as $paramName => $paramDefinition) {
            $key = $positional ? $position++ : $paramName;
            // Not passed: the method's own default applies.
            if (!array_key_exists($key, $params)) continue;
            /** @var Param $paramAttribute */
            if ($paramAttribute = $paramDefinition->getAttributesCollection()->getAttribute(Param::class)) {

                $process = function ($value) use ($paramAttribute, $paramDefinition, $service) {
                    if (is_bool($value) || (!is_scalar($value) && !is_null($value))) {
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
                            'paramDefinition' => $paramDefinition,
                            TypeHintResolver::CLASS_FQCN => $classFQCN ?? $this->resolveParamTypeClassFQCN($paramDefinition->getRealType()),
                        ]
                    );
                };

                if (!$paramAttribute->isCollection()) {
                    try {
                        $params[$key] = $process($params[$key]);
                    } catch (RpcBadParamException $e) {
                        $errors[$paramName][] = ValidateParamsListener::normalizeMessage($e->getMessage());
                    }
                    continue;
                }

                foreach ($params[$key] as $index => $item) {
                    try {
                        $params[$key][$index] = $process($item);
                    } catch (RpcBadParamException $e) {
                        $errors[$paramName][$index][] = ValidateParamsListener::normalizeMessage($e->getMessage());
                    }
                }
            }
        }

        return $errors;
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
