<?php

namespace Ufo\JsonRpcBundle\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\DataCollector\RouterDataCollector;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\Exceptions\ExceptionToArrayTransformer;
use Ufo\JsonRpcBundle\Server\RpcServer;

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
                'data' => $exceptionToArray->infoByEnvirontment(),
            ],
            'jsorpc' => RpcServer::VERSION_2
        ];

        $event->setResponse(new JsonResponse($responseData, 200));
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }
}
