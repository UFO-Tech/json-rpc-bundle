<?php

namespace Ufo\JsonRpcBundle\Controller;

use Laminas\Json\Server\Request as JsonRequest;
use Laminas\Json\Server\Smd;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Ufo\JsonRpcBundle\Exceptions\RpcBadRequestException;
use Ufo\JsonRpcBundle\Exceptions\RpcInvalidButchRequestExceptions;
use Ufo\JsonRpcBundle\Exceptions\RpcTokenNotFoundInHeaderException;
use Ufo\JsonRpcBundle\Interfaces\IFacadeRpcServer;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\JsonRpcBundle\Server\RpcRequestObject;
use Ufo\JsonRpcBundle\SoupUi\ProjectGenerator;

/**
 * Class ApiController
 * @package Ufo\JsonRpcBundle\Controller
 */
class ApiController extends AbstractController
{
    const API_ROUTE = 'ufo_rpc_api_server';

    public function __construct(
        protected IFacadeRpcServer  $rpcServerFacade,
        protected ProjectGenerator  $soupUiProjectGenerator,
        protected RpcRequestHandler $requestHandler
    )
    {
    }

    /**
     * @param Request $request
     * @return Response
     * @throws \Ufo\JsonRpcBundle\Exceptions\RpcBadRequestException
     */
    #[Route('', name: self::API_ROUTE, methods: ["GET", "POST"], format: 'json')]
    public function serverAction(Request $request): Response
    {
        return new JsonResponse($this->requestHandler->handle($request));
    }

    /**
     * @return Response
     */
    #[Route('/soapui.xml', name: 'ufo_rpc_api_soapui_xml', methods: ["GET"], format: 'xml')]
    public function soapUiAction(): Response
    {
        /** @var Smd $smd */
        $smd = $this->rpcServerFacade->getServer()->getServiceMap();

        foreach ($smd->getServices() as $key => $service) {
            $this->soupUiProjectGenerator->addService($service);
        }

        return new Response(
            $this->soupUiProjectGenerator->createXml(),
            headers: ['Content-Type' => 'text/xml']
        );
    }
}