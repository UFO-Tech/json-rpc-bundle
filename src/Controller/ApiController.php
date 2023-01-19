<?php

namespace Ufo\JsonRpcBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    /**
     * @var ZendJsonRpcServerFacade
     */
    private $rpcServerFacade;

    /**
     * @var ProjectGenerator
     */
    private $soupUiProjectGenerator;

    /**
     * ApiController constructor.
     *
     * @param ZendJsonRpcServerFacade $rpcServerFacade
     * @param ProjectGenerator $soupUiProjectGenerator
     */
    public function __construct(ZendJsonRpcServerFacade $rpcServerFacade, ProjectGenerator $soupUiProjectGenerator)
    {
        $this->rpcServerFacade = $rpcServerFacade;
        $this->soupUiProjectGenerator = $soupUiProjectGenerator;
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function serverAction(Request $request)
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
     */
    public function butchRequestAction(Request $request)
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

        return new Response($result, 200, ['Content-Type' => 'application/json']);
    }

    /**
     * @return Response
     */
    public function soapUiAction()
    {
        /** @var Smd $smd */
        $smd = $this->rpcServerFacade->getServer()->getServiceMap();

        foreach ($smd->getServices() as $key => $service) {
            $this->soupUiProjectGenerator->addService($service);
        }

        return new Response($this->soupUiProjectGenerator->createXml(), 200, ['Content-Type' => 'text/xml']);
    }
}