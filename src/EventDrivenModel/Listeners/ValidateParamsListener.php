<?php

namespace Ufo\JsonRpcBundle\EventDrivenModel\Listeners;


use Psr\Container\ContainerInterface;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Server\ServiceMap\IServiceHolder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\RpcError\ConstraintsImposedException;
use Ufo\RpcError\RpcBadParamException;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreExecuteEvent;
use Ufo\RpcObject\Rules\Validator\RpcValidator;

use function array_key_exists;
use function array_reduce;
use function count;
use function is_string;
use function key;
use function sprintf;

#[AsEventListener(RpcEvent::PRE_EXECUTE, 'validateAndPrepareNamedParams', priority: 1002)]
#[AsEventListener(RpcEvent::PRE_EXECUTE, 'validateAndPrepareOrderedParams', priority: 1002)]
#[AsEventListener(RpcEvent::PRE_EXECUTE, 'constraintValidation', priority: 1001)]
class ValidateParamsListener
{
    public const string INVALID_DATA_FOR_CALL_METHOD_TEMPLATE = 'Invalid Data for call method: %s';

    public function __construct(
        protected RpcEventFactory $eventFactory,
        protected RpcValidator $rpcValidator,

        #[Autowire(service: IServiceHolder::LOCATOR)]
        protected ContainerInterface $serviceLocator,
        #[Autowire(service: IServiceHolder::ARG_LOCATOR)]
        protected ContainerInterface $rpcArgumentLocators,
    ) {}

    public function constraintValidation(RpcPreExecuteEvent $event): void
    {
        if ($event->rpcRequest->hasError()) return;
        try {
            $this->rpcValidator->validateMethodParams(
                $this->serviceLocator->get($event->service->getProcedureFQCN()),
                $event->service->getMethodName(),
                $event->params
            );
        } catch (ConstraintsImposedException $e) {
            $this->eventFactory->fireError($event->rpcRequest, new ConstraintsImposedException(
                sprintf(static::INVALID_DATA_FOR_CALL_METHOD_TEMPLATE, $event->rpcRequest->getMethod()),
                $e->getConstraintsImposed()
            ));
            $event->stopPropagation();
        }
    }

    public function validateAndPrepareOrderedParams(RpcPreExecuteEvent $event): void
    {
        $request = $event->rpcRequest;
        $service = $event->service;
        if (is_string(key($request->getParams()))) {
            return;
        }

        $requiredParamsCount = array_reduce($service->getParams(), static function (int $count, ParamDefinition $param) {
            $count += $param->isOptional() ? 0 : 1;

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
        $refMethod = new ReflectionMethod($service->getProcedureFQCN(), $service->getMethodName());

        /** @var ContainerInterface $container */
        $container = $this->rpcArgumentLocators->get($service->procedureFQCN.'::'.$service->getMethodName());

        $errors = [];

        foreach ($refMethod->getParameters() as $refParam) {
            if (array_key_exists($refParam->getName(), $requestedParams)) {
                $namedParams[$refParam->getName()] = $requestedParams[$refParam->getName()];
                continue;
            }

            if ($refParam->isOptional()) {
                $namedParams[$refParam->getName()] = $refParam->getDefaultValue();
                continue;
            }


            if ($container->has($refParam->getName())) {
                try {
                    $namedParams[$refParam->getName()] = $container->get($refParam->getName());
                } catch (\Throwable $e) {
                    continue;
                }
                continue;
            }

            $errors[$refParam->getName()] = sprintf('Required parameter "%s" not passed', $refParam->getName());
        }

        if (count($errors)) {
            $this->eventFactory->fireError($event->rpcRequest, new ConstraintsImposedException(
                sprintf(static::INVALID_DATA_FOR_CALL_METHOD_TEMPLATE, $event->rpcRequest->getMethod()),
                $errors
            ));
            $event->stopPropagation();
        }

        $event->params = $namedParams;
    }

    
    public static function normalizeMessage(string $message): string
    {
        return preg_replace('/[a-zA-Z0-9_\\\\]+\\\\([a-zA-Z0-9_]+)/', '$1', $message);
    }
}
