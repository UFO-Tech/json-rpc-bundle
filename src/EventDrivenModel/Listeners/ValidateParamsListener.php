<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use ReflectionException;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcObject\Events\RpcErrorEvent;
use Ufo\RpcObject\Events\RpcEvent;
use Ufo\RpcObject\Events\RpcPreExecuteEvent;
use Ufo\RpcObject\Events\RpcRequestEvent;
use Ufo\RpcObject\RpcError;
use Ufo\RpcObject\Rules\Validator\ConstraintsImposedException;
use Ufo\RpcObject\Rules\Validator\RpcValidator;

use function array_key_exists;
use function array_reduce;
use function count;
use function is_string;
use function key;
use function sprintf;

#[AsEventListener(RpcEvent::PRE_EXECUTE, 'validateAndPrepareNamedParams', priority: 1001)]
#[AsEventListener(RpcEvent::PRE_EXECUTE, 'validateAndPrepareOrderedParams', priority: 1001)]
#[AsEventListener(RpcEvent::PRE_EXECUTE, 'constraintValidation', priority: 1000)]
#[AsEventListener(RpcEvent::ERROR, 'onConstraintsImpostError', priority: 1000)]
class ValidateParamsListener
{
    public function __construct(
        #[Autowire('kernel.environment')]
        protected string $environment,
        protected RpcEventFactory $eventFactory,
        protected RpcValidator $rpcValidator,
    ) {}

    public function constraintValidation(RpcPreExecuteEvent $event): void
    {
        if ($event->rpcRequest->hasError()) return;
        try {
            $this->rpcValidator->validateMethodParams(
                $event->service->getProcedure(),
                $event->service->getMethodName(),
                $event->params
            );
        } catch (ConstraintsImposedException $e) {
            $this->eventFactory->fireError($event->rpcRequest, $e);
            $event->stopPropagation();
        }
    }

    public function onConstraintsImpostError(RpcErrorEvent $event): void
    {
        $e = $event->exception;
        if (!$e instanceof ConstraintsImposedException) {
            return;
        }
        $event->rpcError = new RpcError($e->getCode(), $e->getMessage(), $e->getConstraintsImposed());
    }


    public function validateAndPrepareOrderedParams(RpcPreExecuteEvent $event): void
    {
        $request = $event->rpcRequest;
        $service = $event->service;
        if (is_string(key($request->getParams()))) {
            return;
        }

        $requiredParamsCount = array_reduce($service->getParams(), static function ($count, $param) {
            $count += $param['optional'] ? 0 : 1;

            return $count;
        }, 0);

        if (count($request->getParams()) < $requiredParamsCount) {
            throw new RpcBadParamException(sprintf('Passed (%s) parameters and expected (%s)',
                count($request->getParams()), $requiredParamsCount));
        }
    }

    /**
     * @throws ReflectionException
     * @throws RpcBadParamException
     */
    public function validateAndPrepareNamedParams(RpcPreExecuteEvent $event): void
    {
        $request = $event->rpcRequest;
        $service = $event->service;
        if (!is_string(key($request->getParams()))) {
            return;
        }

        $requestedParams = $request->getParams();
        if (count($request->getParams()) < count($service->getParams())) {
            $requestedParams = $service->getDefaultParams($request->getParams());
        }
        $namedParams = [];
        $refMethod = new ReflectionMethod($service->getProcedure(), $service->getMethodName());
        foreach ($refMethod->getParameters() as $refParam) {
            if (array_key_exists($refParam->getName(), $requestedParams)) {
                $namedParams[$refParam->getName()] = $requestedParams[$refParam->getName()];
                continue;
            }
            if ($refParam->isOptional()) {
                $namedParams[$refParam->getName()] = $refParam->getDefaultValue();
                continue;
            }
            throw new RpcBadParamException(sprintf('Required parameter "%s" not passed', $refParam->getName()));
        }

        $event->params = $namedParams;
    }

}
