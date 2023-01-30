<?php
/**
 * @author Doctor <ashterix69@gmail.com>
 *
 *
 * Date: 05.06.2017
 * Time: 12:53
 */

namespace Ufo\JsonRpcBundle\Server;


use Laminas\Json\Server\Request;
use Laminas\Json\Server\Response;
use Laminas\Server\Method\Definition;
use Psr\Log\LoggerInterface;
use Laminas\Json\Server\Error;
use Laminas\Json\Server\Server;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\JsonRpcBundle\Exceptions\AbstractJsonRpcBundleException;
use Ufo\JsonRpcBundle\Exceptions\RpcBadParamException;
use Ufo\JsonRpcBundle\Exceptions\RpcMethodNotFoundExceptionRpc;
use Ufo\JsonRpcBundle\Exceptions\RuntimeException;

class RpcServer extends Server
{

    protected RpcRequestObject $requestObject;

    public function __construct(
        protected SerializerInterface $serializer,         
        protected ?LoggerInterface $logger = null
    )
    {
        parent::__construct();
    }

    public function newRequest(RpcRequestObject $requestObject)
    {
        $this->requestObject = $requestObject;
    }

    /**
     * @param string|null $message
     * @param int|string $code
     * @param mixed|null $data
     * @return RpcErrorObject
     */
    public function handleError(
        ?string $message = null,
        int|string $code = AbstractJsonRpcBundleException::DEFAULT_CODE, 
        mixed $data = null
    ): RpcResponseObject
    {
        $error = new RpcErrorObject($code, $message, $data);
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->error(
                (string)$error->getData(),
                [
                    'error' => $message,
                    'method' => $this->requestObject->getMethod(),
                    'params' => $this->requestObject->getParams()
                ]
            );
        }
        return new RpcResponseObject(
            id: $this->requestObject->getId(),
            error: $error,
            version: $this->requestObject->getVersion(),
            requestObject: $this->requestObject
        );
    }

    public function handleRpcRequest(RpcRequestObject $request): RpcResponseObject
    {
        $method = $request->getMethod();

        if (! $this->table->hasMethod($method)) {
            throw new RpcMethodNotFoundExceptionRpc();
        }
        $invokable  = $this->table->getMethod($method);

        $serviceMap = $this->getServiceMap();
        $service    = $serviceMap->getService($method);
        $params     = $this->validateAndPrepareParams($request->getParams(), $service->getParams(), $invokable);

        try {
            $result = $this->_dispatch($invokable, $this->serializer->normalize($params));
        } catch (\Throwable $e) {
            throw RuntimeException::fromThrowable($e);
        }

        return new RpcResponseObject(
            id: $request->getId(),
            result: $result,
            version: $request->getVersion(),
            requestObject: $request
        );
    }

    private function validateAndPrepareParams(
        array $requestedParams,
        array $serviceParams,
        Definition $invokable
    ) {
        return is_string(key($requestedParams))
            ? $this->validateAndPrepareNamedParams($requestedParams, $serviceParams, $invokable)
            : $this->validateAndPrepareOrderedParams($requestedParams, $serviceParams);
    }

    /**
     * Ensures named parameters are passed in the correct order.
     *
     * @param array $requestedParams
     * @param array $serviceParams
     * @return array|Error Array of parameters to use when calling the requested
     *     method on success, Error if any named request parameters do not match
     *     those of the method requested.
     */
    private function validateAndPrepareNamedParams(
        array $requestedParams,
        array $serviceParams,
        Definition $invokable
    ) {
        if (count($requestedParams) < count($serviceParams)) {
            $requestedParams = $this->getDefaultParams($requestedParams, $serviceParams);
        }

        $callback   = $invokable->getCallback();
        $reflection = 'function' === $callback->getType()
            ? new \ReflectionFunction($callback->getFunction())
            : new \ReflectionMethod($callback->getClass(), $callback->getMethod());

        $orderedParams = [];
        foreach ($reflection->getParameters() as $refParam) {
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
     * @param array $requestedParams
     * @param array $serviceParams
     * @return array|Error Array of parameters to use when calling the requested
     *     method on success, Error if the number of request parameters does not
     *     match the number of parameters required by the requested method.
     */
    private function validateAndPrepareOrderedParams(array $requestedParams, array $serviceParams)
    {
        $requiredParamsCount = array_reduce($serviceParams, static function ($count, $param) {
            $count += $param['optional'] ? 0 : 1;
            return $count;
        }, 0);

        if (count($requestedParams) < $requiredParamsCount) {
            throw new RpcBadParamException(
                sprintf(
                    'Passed (%s) parameters and expected (%s)',
                    count($requestedParams), $requiredParamsCount
                )
            );
        }

        return $requestedParams;
    }
}