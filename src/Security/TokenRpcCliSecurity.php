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


use Ufo\JsonRpcBundle\Exceptions\RpcInvalidTokenException;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator;

class TokenRpcCliSecurity implements IRpcSecurity
{
    /**
     * @var ?string
     */
    protected ?string $token = null;

    public function __construct(protected ITokenValidator $tokenValidator)
    {
    }

    /**
     * @param string $token
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * @param $token
     * @return bool
     * @throws RpcInvalidTokenException
     */
    public function isValidToken($token): bool
    {
        $result = $this->tokenValidator->isValid($token);
        if ($result) {
            $this->token = $token;
        }
        return $result;
    }

    /**
     * @return bool
     * @throws RpcInvalidTokenException
     */
    public function isValidRequest(): bool
    {
        return $this->isValidToken($this->token);
    }

    public function getTokenHeaderKey(): string
    {
        return '';
    }

    public function getToken(): string
    {
        return $this->token;
    }
}