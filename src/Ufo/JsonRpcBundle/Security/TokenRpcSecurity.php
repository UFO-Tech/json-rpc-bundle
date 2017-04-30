<?php
/**
 * @author Doctor <doctor.netpeak@gmail.com>
 *
 *
 * Date: 21.04.2017
 * Time: 9:21
 */

namespace Ufo\JsonRpcBundle\Security;


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Ufo\JsonRpcBundle\Exceptions\InvalidTokenException;
use Ufo\JsonRpcBundle\Exceptions\TokenNotFoundInHeaderException;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator;

class TokenRpcSecurity implements IRpcSecurity
{
    /**
     * @var bool
     */
    protected $protectedGet;

    /**
     * @var bool
     */
    protected $protectedPost;

    /**
     * @var string
     */
    protected $tokenHeader;

    /**
     * @var ITokenValidator
     */
    protected $tokenValidator;

    /**
     * @var Request
     */
    protected $request;

    /**
     * TokenRpcSecurity constructor.
     * @param RequestStack $requestStack
     * @param bool $protectedGet
     * @param bool $protectedPost
     * @param string $tokenHeaderKey
     * @param array $tokenValidator
     */
    public function __construct(RequestStack $requestStack, $protectedGet, $protectedPost, $tokenHeaderKey, ITokenValidator $tokenValidator)
    {
        $this->request = $requestStack->getCurrentRequest();
        $this->protectedGet = $protectedGet;
        $this->protectedPost = $protectedPost;
        $this->tokenValidator = $tokenValidator;
        $this->tokenHeader = $tokenHeaderKey;
    }

    /**
     * @return bool
     */
    public function isProtectedGet()
    {
        return $this->protectedGet;
    }

    /**
     * @return bool
     */
    public function isProtectedPost()
    {
        return $this->protectedPost;
    }

    /**
     * @param $token
     * @return bool
     * @throws InvalidTokenException
     */
    public function isValidToken($token)
    {
        return $this->tokenValidator->isValid($token);
    }

    /**
     * @return bool
     * @throws InvalidTokenException
     * @throws TokenNotFoundInHeaderException
     */
    protected function isValidRequest()
    {
        $token = Helper::tokenFromRequest($this->request, $this->getTokenHeader());
        return $this->isValidToken($token);
    }

    /**
     * @return string
     */
    public function getTokenHeader()
    {
        return $this->tokenHeader;
    }

    /**
     * @return bool
     * @throws InvalidTokenException
     * @throws TokenNotFoundInHeaderException
     */
    public function isValidGetRequest()
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
    public function isValidPostRequest()
    {
        if ($this->isProtectedPost()) {
            $this->isValidRequest();
        }
        return true;
    }
}