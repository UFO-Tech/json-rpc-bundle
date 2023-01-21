<?php
/**
 * @author Doctor <ashterix69@gmail.com>
 *
 *
 * Date: 21.04.2017
 * Time: 9:21
 */

namespace Ufo\JsonRpcBundle\Security;


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Ufo\JsonRpcBundle\Exceptions\InvalidTokenException;
use Ufo\JsonRpcBundle\Exceptions\TokenNotFoundInHeaderException;
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
     * @param bool $protectedGet
     * @param bool $protectedPost
     * @param string $tokenHeaderKey
     * @param ITokenValidator $tokenValidator
     * @param ?RouterInterface $router
     */
    public function __construct(
        RequestStack $requestStack,
        protected bool $protectedGet,
        protected bool $protectedPost,
        protected string $tokenHeaderKey,
        protected ITokenValidator $tokenValidator,
        ?RouterInterface $router = null
    )
    {
        $this->request = $requestStack->getCurrentRequest();
        $this->tokenHeader = $tokenHeaderKey;
        if (!is_null($router)) {
            $this->protectedPath = $router->getRouteCollection()->get('ufo_rpc_api_server')->getPath();
        }
    }

    /**
     * @return bool
     */
    public function isProtectedGet(): bool
    {
        return $this->protectedGet;
    }

    /**
     * @return bool
     */
    public function isProtectedPost(): bool
    {
        return $this->protectedPost;
    }

    /**
     * @param $token
     * @return bool
     * @throws InvalidTokenException
     */
    public function isValidToken($token): bool
    {
        return $this->tokenValidator->isValid($token);
    }

    /**
     * @return bool
     * @throws InvalidTokenException
     * @throws TokenNotFoundInHeaderException
     */
    protected function isValidRequest(): bool
    {
        $res = true;
        if ($this->routeMustBeProtected()) {
            $token = Helper::tokenFromRequest($this->request, $this->getTokenHeader());
            $res = $this->isValidToken($token);
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
    public function getTokenHeader(): string
    {
        return $this->tokenHeader;
    }

    /**
     * @return bool
     * @throws InvalidTokenException
     * @throws TokenNotFoundInHeaderException
     */
    public function isValidGetRequest(): bool
    {
        if ($this->isProtectedGet()) {
            $this->isValidRequest();
        }
        return true;
    }

    /**
     * @return bool
     * @throws InvalidTokenException
     * @throws TokenNotFoundInHeaderException
     */
    public function isValidPostRequest(): bool
    {
        if ($this->isProtectedPost()) {
            $this->isValidRequest();
        }
        return true;
    }
}