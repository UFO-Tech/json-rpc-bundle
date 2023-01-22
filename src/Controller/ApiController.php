<?php

namespace Ufo\JsonRpcBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Ufo\JsonRpcBundle\Facade\Interfaces\IFacadeJsonRpcServer;
use Ufo\JsonRpcBundle\Facade\ZendJsonRpcServerFacade;
use Ufo\JsonRpcBundle\SoupUi\ProjectGenerator;
use Ufo\JsonRpcBundle\Exceptions\InvalidButchRequestExceptions;
use Laminas\Json\Server\Smd;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class ApiController
 * @package Ufo\JsonRpcBundle\Controller
 */
class ApiController extends AbstractController
{
    const API_ROUTE = 'ufo_rpc_api_server';

    /**
     * @var IFacadeJsonRpcServer
     */
    private IFacadeJsonRpcServer $rpcServerFacade;

    /**
     * @var ProjectGenerator
     */
    private ProjectGenerator $soupUiProjectGenerator;

    /**
     * ApiController constructor.
     *
     * @param IFacadeJsonRpcServer $rpcServerFacade
     * @param ProjectGenerator $soupUiProjectGenerator
     */
    public function __construct(IFacadeJsonRpcServer $rpcServerFacade, ProjectGenerator $soupUiProjectGenerator)
    {
        $this->rpcServerFacade = $rpcServerFacade;
        $this->soupUiProjectGenerator = $soupUiProjectGenerator;
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('', name: self::API_ROUTE, methods: ["GET", "POST"])]
    public function serverAction(Request $request): Response
    {
        if (Request::METHOD_GET == $request->getMethod()) {
            $smd = $this->rpcServerFacade->getServiceMap();
            return new Response($smd, 200, ['Content-Type' => 'application/json']);
        }
        $this->rpcServerFacade->getServer()->setReturnResponse(true);

        try {
            return $this->butchRequestAction($request);
        } catch (InvalidButchRequestExceptions $e) {
            return new Response($this->rpcServerFacade->handle()->toJson(), 200, ['Content-Type' => 'application/json']);
        }
    }

    /**
     * @param Request $request
     * @return Response
     * @throws InvalidButchRequestExceptions
     */
    public function butchRequestAction(Request $request): Response
    {
        $raw = json_decode($request->getContent(), true);
        if (false === isset($raw[0])
            || false === is_array($raw[0])) {
            throw new InvalidButchRequestExceptions();
        }

        $responses = [];
        foreach ($raw as $options) {
            $this->rpcServerFacade->getServer()->clearRequestAndResponse();
            $this->rpcServerFacade->getServer()->getRequest()->setOptions($options);

            $responses[] = $this->rpcServerFacade->handle()->toJson();
        }
        $result = '[' . implode(', ', $responses) . ']';

        return new JsonResponse($result);
    }

    /**
     * @return Response
     */
    #[Route('/soapui.xml', name: 'ufo_rpc_api_soapui_xml', methods: ["GET", "POST"])]
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