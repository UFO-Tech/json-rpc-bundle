<?php

namespace Ufo\JsonRpcBundle\Security;

namespace Ufo\JsonRpcBundle\Security;

use AllowDynamicProperties;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcTokenNotFoundInHeaderException;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator;

use function in_array;

#[AllowDynamicProperties]
class TokenRpcSecurity implements IRpcSecurity
{
    /**
     * @var string
     */
    protected string $tokenHeader = '';
    /**
     * @var Request
     */
    protected Request $request;
    protected RouteCollection $protectedPath;

    /**
     * @param RequestStack $requestStack
     * @param RpcMainConfig $rpcConfig
     * @param ITokenValidator $tokenValidator
     * @param RouterInterface|null $router
     */
    public function __construct(
        RequestStack $requestStack,
        protected RpcMainConfig $rpcConfig,
        protected ITokenValidator $tokenValidator,
        ?RouterInterface $router = null
    ) {
        $this->request = $requestStack->getCurrentRequest();
        if (!is_null($router)) {
            $this->protectedPath = new RouteCollection();
            $this->protectedPath->add(ApiController::API_ROUTE,
                $router->getRouteCollection()->get(ApiController::API_ROUTE));
            $this->protectedPath->add(ApiController::COLLECTION_ROUTE,
                $router->getRouteCollection()->get(ApiController::COLLECTION_ROUTE));
        }
    }

    /**
     * @param $token
     * @return bool
     * @throws RpcInvalidTokenException
     */
    public function isValidToken($token): bool
    {
        return $this->tokenValidator->isValid($token);
    }

    /**
     * @return bool
     * @throws RpcInvalidTokenException
     * @throws RpcTokenNotFoundInHeaderException
     */
    protected function validateRequest(): bool
    {
        $res = true;
        if ($this->routeMustBeProtected()) {
            $token = Helper::tokenFromRequest($this->request, $this->getTokenHeaderKey());
            $res = $this->isValidToken($token);
            $this->tokenHeader = $token;
        }

        return $res;
    }

    /**
     * @return bool
     */
    protected function routeMustBeProtected(): bool
    {
        $isProtected = true;
        $context = new RequestContext('/');
        $matcher = new UrlMatcher($this->protectedPath, $context);
        try {
            $matcher->match($this->request->getPathInfo());
        } catch (ResourceNotFoundException $e) {
            $isProtected = false;
        }

        return $isProtected;
    }

    /**
     * @return string
     */
    public function getTokenHeaderKey(): string
    {
        return $this->rpcConfig->securityConfig->tokenKeyInHeader;
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->tokenHeader;
    }

    /**
     * @return bool
     * @throws RpcInvalidTokenException
     * @throws RpcTokenNotFoundInHeaderException
     */
    public function isValidRequest(): bool
    {
        $requestMethod = $this->request->getMethod();
        if (in_array($requestMethod, $this->rpcConfig->securityConfig->tokens)) {
            $this->validateRequest();
        }

        return true;
    }
}