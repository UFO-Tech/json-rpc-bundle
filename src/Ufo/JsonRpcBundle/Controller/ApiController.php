<?php

namespace Ufo\JsonRpcBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Ufo\JsonRpcBundle\Exceptions\InvalidButchRequestExceptions;
use Zend\Json\Server\Smd;

class ApiController extends Controller
{

    /**
     * @param Request $request
     * @return Response
     */
    public function serverAction(Request $request)
    {
        $server = $this->get('ufo_api_server.zend_json_rpc_server_facade');
        if (Request::METHOD_GET == $request->getMethod()) {
            $smd = $server->getServiceMap();
            return new Response($smd, 200, ['Content-Type' => 'application/json']);
        }
        $server->getServer()->setReturnResponse(true);

        try {
            return $this->butchRequestAction($request);
        } catch (InvalidButchRequestExceptions $e) {
            return new Response($server->handle()->toJson(), 200, ['Content-Type' => 'application/json']);
        }
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function butchRequestAction(Request $request)
    {
        $server = $this->get('ufo_api_server.zend_json_rpc_server_facade');

        $raw = json_decode($request->getContent(), true);
        if (false === isset($raw[0])
            || false === is_array($raw[0])) {
            throw new InvalidButchRequestExceptions();
        }

        $responses = [];
        foreach ($raw as $options) {
            $server->getServer()->clearRequestAndResponse();
            $server->getServer()->getRequest()->setOptions($options);

            $responses[] = $server->handle()->toJson();
        }
        $result = '[' . implode(', ', $responses) . ']';

        return new Response($result, 200, ['Content-Type' => 'application/json']);
    }

    /**
     * @return Response
     */
    public function soapUiAction()
    {
        $smd = $this->get('ufo_api_server.zend_json_rpc_server_facade')->getServer()->getServiceMap();
        $soupUiProjectGenerator = $this->get('ufo_api_server.soupui.project_generator');

        foreach ($smd->getServices() as $key => $service) {
            $soupUiProjectGenerator->addService($service);
        }

        return new Response($soupUiProjectGenerator->createXml(), 200, ['Content-Type' => 'text/xml']);
    }


}
