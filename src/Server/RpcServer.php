<?php

namespace Ufo\JsonRpcBundle\Server;


use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\UfoReflectionProcedure;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceLocator;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\RpcObject\RpcError;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

class RpcServer
{
    const VERSION_1 = '1.0';
    const VERSION_2 = '2.0';
    
    protected RpcRequest $requestObject;

    public function __construct(
        protected SerializerInterface $serializer,
        protected ServiceLocator      $serviceLocator,
        protected ?LoggerInterface    $logger = null
    )
    {
    }

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
    ): RpcResponse
    {
        $error = new RpcError($code, $message, $data);
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->error(
                (string)$error->getData(),
                [
                    'error' => $message,
                    'method' => $this->requestObject->getMethod(),
                    'params' => $this->requestObject->getParams(),
                ]
            );
        }
        return new RpcResponse(
            id: $this->requestObject->getId(),
            error: $error,
            version: $this->requestObject->getVersion(),
            requestObject: $this->requestObject
        );
    }

    /**
     * @param RpcRequest $request
     * @return RpcResponse
     * @throws RpcBadParamException
     * @throws RpcMethodNotFoundExceptionRpc
     * @throws RpcRuntimeException
     * @throws \ReflectionException
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
        } catch (\Throwable $e) {
            throw RpcRuntimeException::fromThrowable($e);
        }

        return new RpcResponse(
            id: $request->getId(),
            result: $result,
            version: $request->getVersion(),
            requestObject: $request
        );
    }

    /**
     * @param IRpcService $procedure
     * @return $this
     */
    public function addProcedure(IRpcService $procedure): static
    {
        $reflection = new UfoReflectionProcedure($procedure);

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
    ): array
    {
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
    ): array
    {
        $requestedParams = $request->getParams();
        if (count($request->getParams()) < count($service->getParams())) {
            $requestedParams = $service->getDefaultParams($service->getParams());
        }

        $orderedParams = [];
        $methodNameArray = explode('.', $request->getMethod());
        $method = end($methodNameArray);
        $refMethod = new \ReflectionMethod($service->getProcedure(), $method);
        foreach ($refMethod->getParameters() as $refParam) {
            if (array_key_exists($refParam->getName(), $requestedParams)) {
                $orderedParams[$refParam->getName()] = $requestedParams[$refParam->getName()];
                continue;
            }

            if ($refParam->isOptional()) {
                $orderedParams[$refParam->getName()] = null;
                continue;
            }

            throw new RpcBadParamException(
                sprintf(
                    'Required parameter "%s" not passed',
                    $refParam->getName()
                )
            );
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
    ): array
    {
        $requiredParamsCount = array_reduce($service->getParams(), static function ($count, $param) {
            $count += $param['optional'] ? 0 : 1;
            return $count;
        }, 0);

        if (count($request->getParams()) < $requiredParamsCount) {
            throw new RpcBadParamException(
                sprintf(
                    'Passed (%s) parameters and expected (%s)',
                    count($request->getParams()), $requiredParamsCount
                )
            );
        }

        return $request->getParams();
    }

    private function dispatch(ServiceMap\Service $service, $params)
    {
        $object = $service->getProcedure();
        return call_user_func_array([
            $object,
            $service->getMethodName(),
        ], $params);
    }
}