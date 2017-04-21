<?php

namespace Ufo\JsonRpcBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiController extends Controller
{
    public function serverAction(Request $request)
    {
        $server = $this->get('ufo_api_server.zend_json_rpc_server_facade');

        if (Request::METHOD_GET == $request->getMethod()) {
            $smd = $server->getServiceMap();
            return new Response($smd, 200, ['Content-Type' => 'application/json']);
        }
        $server->getServer()->setReturnResponse(true);
        return new Response($server->handle()->toJson(), 200, ['Content-Type' => 'application/json']);

    }



}
