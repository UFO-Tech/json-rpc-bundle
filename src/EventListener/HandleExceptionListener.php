<?php

namespace Ufo\JsonRpcBundle\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\Server\RpcServer;
use Ufo\RpcError\ExceptionToArrayTransformer;

class HandleExceptionListener implements EventSubscriberInterface
{

    public function __construct(protected string $environment)
    {
    }

    public function onKernelException(ExceptionEvent $event)
    {
        $apiRoute = $event->getRequest()->attributes->get('_route');
        if (!$apiRoute === ApiController::API_ROUTE) {
            return;
        }
        $exceptionToArray = new ExceptionToArrayTransformer($event->getThrowable(), $this->environment);

        $responseData = [
            'id' => 'uncatchedError',
            'error' => [
                'code' => $event->getThrowable()->getCode(),
                'message' => $event->getThrowable()->getMessage(),
                'data' => $exceptionToArray->infoByEnvironment(),
            ],
            'jsorpc' => RpcServer::VERSION_2
        ];

        $event->setResponse(new JsonResponse($responseData, 200));
        $event->stopPropagation();
        $event->allowCustomResponseCode();
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }
}
