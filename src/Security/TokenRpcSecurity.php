<?php
/**
 * @author Doctor <ashterix69@gmail.com>
 *
 *
 * Date: 21.04.2017
 * Time: 9:21
 */

namespace Ufo\JsonRpcBundle\Security;


namespace Ufo\JsonRpcBundle\Security;


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcTokenNotFoundInHeaderException;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator;

class TokenRpcSecurity implements IRpcSecurity
{
    /**
     * @var string
     */
    protected string $tokenHeader;

    /**
     * @var Request
     */
    protected Request $request;

    /**
     * @var ?string
     */
    protected ?string $protectedPath = null;

    /**
     * @param RequestStack $requestStack
     * @param string $tokenHeaderKey
     * @param ITokenValidator $tokenValidator
     * @param array $protectedMethods
     * @param RouterInterface|null $router
     */
    public function __construct(
        RequestStack $requestStack,
        protected string $tokenHeaderKey,
        protected ITokenValidator $tokenValidator,
        protected array $protectedMethods = [],
        ?RouterInterface $router = null
    )
    {
        $this->request = $requestStack->getCurrentRequest();
        if (!is_null($router)) {
            $this->protectedPath = $router->getRouteCollection()->get(ApiController::API_ROUTE)->getPath();
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
        return $this->protectedPath && $this->request->getRequestUri() == $this->protectedPath;
    }

    /**
     * @return string
     */
    public function getTokenHeaderKey(): string
    {
        return $this->tokenHeaderKey;
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
        if (in_array($requestMethod, $this->protectedMethods)) {
            $this->validateRequest();
        }
        return true;
    }

}