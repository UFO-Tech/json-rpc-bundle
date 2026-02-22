<?php

namespace Ufo\JsonRpcBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\OpenRpcAdapter;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\PostmanAdapter;
use Ufo\RpcObject\RPC\Info;

use function json_encode;

use const JSON_PRETTY_PRINT;

/**
 * Class ApiController
 * @package Ufo\JsonRpcBundle\Controller
 */
class ApiController extends AbstractController
{
    const string API_ROUTE = 'ufo_rpc_api_server';
    const string API_ROUTE_VER = self::API_ROUTE . '_ver';
    const string POSTMAN_ROUTE = 'ufo_rpc_api_postman';
    const string POSTMAN_ROUTE_VER = self::POSTMAN_ROUTE . '_ver';

    const array API_DOC_ROUTES = [
        self::API_ROUTE,
        self::POSTMAN_ROUTE
    ];

    /**
     * @param OpenRpcAdapter $openRpcAdapter
     * @param Request $request
     * @return Response
     */
    #[Route('/{ver<v\d+>}', name: self::API_ROUTE . '_ver', methods: ["GET"], priority: 90, format: 'json')]
    #[Route('', name: self::API_ROUTE, defaults: ['ver' => Info::DEFAULT_VERSION], methods: ["GET"], priority: 100, format: 'json')]
    public function openrpc(OpenRpcAdapter $openRpcAdapter, Request $request): Response
    {
        $result = $openRpcAdapter->adapt(!$request->query->has('info'), $request->attributes->get('ver'));
        return new JsonResponse(json_encode($result, JSON_PRETTY_PRINT), json: true);
    }

    #[Route('/{ver<v\d+>}/postman', name: self::POSTMAN_ROUTE_VER, methods: ["GET"], format: 'json')]
    #[Route('/postman', name: self::POSTMAN_ROUTE, defaults: ['ver' => Info::DEFAULT_VERSION], methods: ["GET"], format: 'json')]
    public function postman(PostmanAdapter $postmanAdapter, Request $request): Response
    {
        $result = $postmanAdapter->adapt($request->attributes->get('ver'));
        return new JsonResponse(json_encode($result, JSON_PRETTY_PRINT), json: true);
    }
}
