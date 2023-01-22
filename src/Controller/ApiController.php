<?php

namespace Ufo\JsonRpcBundle\Controller;

use Laminas\Json\Server\Request as JsonRequest;
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
        $postpone = [];
        foreach ($raw as $options) {
            if (!empty($options['params'])
                && $matched = preg_grep('/^\@FROM\:/i', $options['params'])) {
                $postpone[$options['method']] = [
                    'request' => $options,
                    'match' => $matched
                ];
                continue;
            }
            $response = $this->handleSingleRequestFromButch($options);
            $responses[$response['id']] = $response;
        }
        $this->providePostponeFromButch($postpone, $responses);
        return new JsonResponse(array_values($responses));
    }

    protected function handleSingleRequestFromButch(array $options): array
    {
        $server = $this->rpcServerFacade->getServer();
        $server->clearRequestAndResponse();
        $singleRequest = new JsonRequest();
        $singleRequest->setOptions($options);
        $server->setRequest($singleRequest);
        return json_decode($this->rpcServerFacade->handle()->toJson(), true);
    }

    /**
     * @param array $postpone
     * @param array $responses
     * @return void
     * @throws InvalidButchRequestExceptions
     */
    protected function providePostponeFromButch(array $postpone, array &$responses)
    {
        foreach ($postpone as $item) {
            foreach ($item['match'] as $key => $subject) {
                $manches = [];
                preg_match('/^\@FROM\:(\w+)\((\w+)\)$/i', $subject, $manches);
                if (!isset($responses[$manches[1]])
                    || !isset($responses[$manches[1]]['result'][$manches[2]])
                ) {
                    throw new InvalidButchRequestExceptions('Not found target response for ' . $subject);
                }
                $item['request']['params'][$key] = $responses[$manches[1]]['result'][$manches[2]];
                $response = $this->handleSingleRequestFromButch($item['request']);
                $responses[$response['id']] = $response;
            }
        }
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