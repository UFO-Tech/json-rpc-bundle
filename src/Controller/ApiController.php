<?php

namespace Ufo\JsonRpcBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\OpenRpcAdapter;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\PostmanAdapter;

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

    const array API_DOC_ROUTES = [
        self::API_ROUTE,
        self::POSTMAN_ROUTE
    ];

    /**
     * @param OpenRpcAdapter $openRpcAdapter
     * @return Response
     */
    #[Route('', name: self::API_ROUTE, methods: ["GET"], format: 'json')]
    public function serverAction(OpenRpcAdapter $openRpcAdapter): Response
    {
        $result = $openRpcAdapter->adapt();
        return new JsonResponse(json_encode($result, JSON_PRETTY_PRINT), json: true);
    }

    #[Route('/postman', name: self::POSTMAN_ROUTE, methods: ["GET"], format: 'json')]
    public function postmanAction(PostmanAdapter $postmanAdapter): Response
    {
        $result = $postmanAdapter->adapt();
        return new JsonResponse(json_encode($result, JSON_PRETTY_PRINT), json: true);
    }
}