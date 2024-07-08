<?php

namespace Ufo\JsonRpcBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\OpenRpcAdapter;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceLocator;

use function json_encode;

/**
 * Class ApiController
 * @package Ufo\JsonRpcBundle\Controller
 */
class ApiController extends AbstractController
{
    const API_ROUTE = 'ufo_rpc_api_server';
    const COLLECTION_ROUTE = 'ufo_rpc_api_collection';
    const OPEN_RPC_ROUTE = 'ufo_rpc_api_collection';

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('', name: self::API_ROUTE, methods: ["GET", "POST"], format: 'json')]
    public function serverAction(Request $request, RpcRequestHandler $requestHandler): Response
    {
        return new JsonResponse($requestHandler->handle($request));
    }

    #[Route('/method/{method}', name: self::COLLECTION_ROUTE, methods: ["GET"], format: 'json')]
    public function docsAction(string $method, ServiceLocator $serviceLocator): Response
    {
        $smd = $serviceLocator->getService($method);

        return new JsonResponse($smd->toArray());
    }

    #[Route('/openrpc.json', name: self::OPEN_RPC_ROUTE, methods: ["GET"], format: 'json')]
    public function openRpcAction(OpenRpcAdapter $openRpcAdapter): Response
    {
        $doc = $openRpcAdapter->adapt();

        return new JsonResponse(json_encode($doc, JSON_PRETTY_PRINT), json: true);
    }

}