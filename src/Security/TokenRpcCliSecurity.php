<?php
namespace Ufo\JsonRpcBundle\Security;


namespace Ufo\JsonRpcBundle\Security;


use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator;

class TokenRpcCliSecurity implements IRpcSecurity
{
    /**
     * @var ?string
     */
    protected ?string $token = '';

    public function __construct(
        protected ITokenValidator $tokenValidator,
        protected array $protectedMethods = [],
    )
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
        $result = true;
        if (in_array('POST', $this->protectedMethods)) {
            $result = $this->isValidToken($this->token);
        }
        return $result;
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