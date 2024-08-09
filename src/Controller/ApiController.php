<?php

namespace Ufo\JsonRpcBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\OpenRpcAdapter;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\PostmanAdapter;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceLocator;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcError\RpcJsonParseException;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\RpcError\WrongWayException;

use function json_encode;

use const JSON_PRETTY_PRINT;

/**
 * Class ApiController
 * @package Ufo\JsonRpcBundle\Controller
 */
class ApiController extends AbstractController
{
    const string API_ROUTE = 'ufo_rpc_api_server';
    const string POSTMAN_ROUTE = 'ufo_rpc_api_postman';
    const string COLLECTION_ROUTE = 'ufo_rpc_api_collection';
    const string OPEN_RPC_ROUTE = 'ufo_rpc_api_collection';

    /**
     * @param Request $request
     * @param RpcRequestHandler $requestHandler
     * @param OpenRpcAdapter $openRpcAdapter
     * @return Response
     * @throws RpcJsonParseException
     * @throws WrongWayException
     * @throws RpcAsyncRequestException
     * @throws RpcMethodNotFoundExceptionRpc
     * @throws RpcRuntimeException
     */
    #[Route('', name: self::API_ROUTE, methods: ["GET", "POST"], format: 'json')]
    public function serverAction(Request $request, RpcRequestHandler $requestHandler, OpenRpcAdapter $openRpcAdapter): Response
    {
        if ($request->getMethod() === Request::METHOD_POST) {
            $result = $requestHandler->handle($request);
        } else {
            $result = $openRpcAdapter->adapt();
        }
        return new JsonResponse(json_encode($result, JSON_PRETTY_PRINT), json: true);
    }

    #[Route('/postman', name: self::POSTMAN_ROUTE, methods: ["GET"], format: 'json')]
    public function postmanAction(PostmanAdapter $postmanAdapter): Response
    {
        $result = $postmanAdapter->adapt();
        return new JsonResponse(json_encode($result, JSON_PRETTY_PRINT), json: true);
    }

}