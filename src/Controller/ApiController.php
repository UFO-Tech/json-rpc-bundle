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

use function in_array;
use function is_array;
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
        self::POSTMAN_ROUTE,
        self::API_ROUTE_VER,
        self::POSTMAN_ROUTE_VER
    ];

    const array API_ROUTES = [
        self::API_ROUTE,
        self::API_ROUTE_VER,
    ];

    /**
     * @param OpenRpcAdapter $openRpcAdapter
     * @param Request $request
     * @return Response
     */
    #[Route('/{ver<v\d+>}', name: self::API_ROUTE . '_ver', defaults: ['_rpc_route' => self::API_ROUTE_VER], methods: ['GET'], priority: 90, format: 'json')]
    #[Route('', name: self::API_ROUTE, defaults: ['ver' => Info::DEFAULT_VERSION, '_rpc_route' => self::API_ROUTE], methods: ['GET'], priority: 100, format: 'json')]
    public function openrpc(OpenRpcAdapter $openRpcAdapter, Request $request): Response
    {
        $result = $openRpcAdapter->adapt(!$request->query->has('info'), $request->attributes->get('ver'));
        return new JsonResponse(json_encode($result, JSON_PRETTY_PRINT), json: true);
    }

    #[Route('/{ver<v\d+>}/postman', name: self::POSTMAN_ROUTE_VER, defaults: ['_rpc_route' => self::POSTMAN_ROUTE_VER], methods: ['GET'], format: 'json')]
    #[Route('/postman', name: self::POSTMAN_ROUTE, defaults: ['ver' => Info::DEFAULT_VERSION, '_rpc_route' => self::POSTMAN_ROUTE], methods: ['GET'], format: 'json')]
    public function postman(PostmanAdapter $postmanAdapter, Request $request): Response
    {
        $result = $postmanAdapter->adapt($request->attributes->get('ver'));
        return new JsonResponse(json_encode($result, JSON_PRETTY_PRINT), json: true);
    }

    public static function isDocsRoute(array|string $route): bool
    {
        if (is_array($route)) $route = static::routeFromArray($route);
        return in_array($route, ApiController::API_DOC_ROUTES, strict: true);
    }

    public static function isApiRoute(array|string $route): bool
    {
        if (is_array($route)) $route = static::routeFromArray($route);
        return in_array($route, ApiController::API_ROUTES, strict: true);
    }

    public static function routeFromArray(array $route): string
    {
        return $route['_rpc_route'] ?? $route['_route'] ?? '';
    }
}
