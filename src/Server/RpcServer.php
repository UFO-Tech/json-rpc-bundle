<?php

namespace Ufo\JsonRpcBundle\Server;

use Psr\Log\LoggerInterface;
use ReflectionException;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\RpcObject\Rules\Validator\RpcValidator;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\UfoReflectionProcedure;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceLocator;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RpcError;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

use function call_user_func_array;
use function implode;
use function is_array;

class RpcServer
{
    const VERSION_1 = '1.0';
    const VERSION_2 = '2.0';

    protected ?RpcRequest $requestObject = null;

    public function __construct(
        protected SerializerInterface $serializer,
        protected ServiceLocator $serviceLocator,
        protected RpcValidator $rpcValidator,
        protected RpcMainConfig $rpcConfig,
        protected ?LoggerInterface $logger = null
    ) {}

    /**
     * @return ServiceLocator
     */
    public function getServiceLocator(): ServiceLocator
    {
        return $this->serviceLocator;
    }

    public function newRequest(RpcRequest $requestObject): void
    {
        $this->requestObject = $requestObject;
    }

    /**
     * @param string|null $message
     * @param int|string $code
     * @param mixed|null $data
     * @return RpcResponse
     */
    public function handleError(
        ?string $message = null,
        int|string $code = AbstractRpcErrorException::DEFAULT_CODE,
        mixed $data = null
    ): RpcResponse {
        $error = new RpcError($code, $message, $data);
        if ($this->logger instanceof LoggerInterface) {
            $isData = $error->getData();
            if (is_array($isData)) {
                $isData = $this->serializer->serialize($isData, 'yaml');
            }
            $this->logger->error((string)$isData, [
                'error'  => $message,
                'method' => $this->requestObject?->getMethod(),
                'params' => $this->requestObject?->getParams(),
            ]);
        }

        return new RpcResponse(
            id: $this->requestObject?->getId() ?? 'not_processed',
            error: $error,
            version: $this->requestObject?->getVersion() ?? 'not_processed',
            requestObject: $this->requestObject);
    }

    /**
     * @throws RpcBadParamException
     * @throws RpcRuntimeException
     * @throws ReflectionException
     * @throws AbstractRpcErrorException
     * @throws RpcMethodNotFoundExceptionRpc
     */
    public function handleRpcRequest(RpcRequest $request): RpcResponse
    {
        $method = $request->getMethod();
        try {
            $service = $this->serviceLocator->get($method);
        } catch (ServiceNotFoundException $e) {
            throw new RpcMethodNotFoundExceptionRpc($e->getMessage());
        }
        $params = $this->validateAndPrepareParams($request, $service);
        try {
            $result = $this->dispatch($service, $this->serializer->normalize($params));
        } catch (\TypeError $e) {
            $message = preg_replace('/.*\\\\/', '', $e->getMessage());
            $message = preg_replace('/Argument #\d+ \(\$([a-zA-Z0-9_]+)\)/', 'Parameter "$1"', $message);
            throw new RpcBadParamException($message);
        } catch (\Throwable $e) {
            throw RpcRuntimeException::fromThrowable($e);
        }

        return new RpcResponse(
            id: $request->getId(),
            result: $result,
            version: $request->getVersion(),
            requestObject: $request, cache: $service->getCacheInfo()
        );
    }

    /**
     * @param IRpcService $procedure
     * @return $this
     */
    public function addProcedure(IRpcService $procedure): static
    {
        $reflection = new UfoReflectionProcedure($procedure, $this->serializer, $this->rpcConfig->docsConfig);
        foreach ($reflection->getMethods() as $service) {
            $this->getServiceLocator()->addService($service);
        }

        return $this;
    }

    /**
     * @param RpcRequest $request
     * @param Service $service
     * @return array
     * @throws RpcBadParamException
     * @throws \ReflectionException
     */
    private function validateAndPrepareParams(
        RpcRequest $request,
        Service $service
    ): array {
        return is_string(key($request->getParams()))
            ? $this->validateAndPrepareNamedParams($request, $service)
            : $this->validateAndPrepareOrderedParams($request, $service);
    }

    /**
     * @param RpcRequest $request
     * @param Service $service
     * @return array
     * @throws RpcBadParamException
     * @throws \ReflectionException
     */
    private function validateAndPrepareNamedParams(
        RpcRequest $request,
        Service $service
    ): array {
        $requestedParams = $request->getParams();
        if (count($request->getParams()) < count($service->getParams())) {
            $requestedParams = $service->getDefaultParams($request->getParams());
        }
        $orderedParams = [];
        $refMethod = new \ReflectionMethod($service->getProcedure(), $service->getMethodName());
        foreach ($refMethod->getParameters() as $refParam) {
            if (array_key_exists($refParam->getName(), $requestedParams)) {
                $orderedParams[$refParam->getName()] = $requestedParams[$refParam->getName()];
                continue;
            }
            if ($refParam->isOptional()) {
                $orderedParams[$refParam->getName()] = $refParam->getDefaultValue();
                continue;
            }
            throw new RpcBadParamException(sprintf('Required parameter "%s" not passed', $refParam->getName()));
        }

        return $orderedParams;
    }

    /**
     * @param RpcRequest $request
     * @param Service $service
     * @return array
     * @throws RpcBadParamException
     */
    private function validateAndPrepareOrderedParams(
        RpcRequest $request,
        Service $service
    ): array {
        $requiredParamsCount = array_reduce($service->getParams(), static function ($count, $param) {
            $count += $param['optional'] ? 0 : 1;

            return $count;
        }, 0);
        if (count($request->getParams()) < $requiredParamsCount) {
            throw new RpcBadParamException(sprintf('Passed (%s) parameters and expected (%s)',
                count($request->getParams()), $requiredParamsCount));
        }

        return $request->getParams();
    }

    private function dispatch(Service $service, array $params): mixed
    {
        $object = $service->getProcedure();
        try {
            $this->rpcValidator->validateMethodParams($object, $service->getMethodName(), $params);
            $result = call_user_func_array([
                $object,
                $service->getMethodName(),
            ], $params);
        } catch (AbstractRpcErrorException $e) {
            $this->requestObject->setError($e);
            $result = null;
        }

        return $result;
    }

}