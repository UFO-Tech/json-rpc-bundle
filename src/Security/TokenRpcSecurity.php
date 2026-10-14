<?php
namespace Ufo\JsonRpcBundle\Security;

use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcTokenHolder;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcTokenNotSentException;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\RpcObject\RPC\APIGroups;

class TokenRpcSecurity implements IRpcSecurity
{
    /**
     * @var string
     */
    protected string $token = '';

    protected ?IRpcTokenHolder $holder = null;

    /**
     * @param RpcMainConfig $rpcConfig
     * @param ITokenValidator $tokenValidator
     */
    public function __construct(
        protected RpcMainConfig $rpcConfig,
        protected ITokenValidator $tokenValidator,
        protected ?ApiGroupContext $apiGroupContext = null,
        protected ?TokenValidatorRegistry $validatorRegistry = null,
    ) {}

    public function setTokenHolder(IRpcTokenHolder $holder): self
    {
        $this->holder = $holder;
        return $this;
    }

    /**
     * @return bool
     * @throws RpcInvalidTokenException
     * @throws RpcTokenNotSentException
     */
    public function isValidDocRequest(): true
    {
        if ($this->rpcConfig->securityConfig->protectedDoc) {
            $this->getValidator()->isValid($this->getTokenHolder()->getToken());
        }
        return true;
    }

    /**
     * @return bool
     * @throws RpcInvalidTokenException
     * @throws RpcTokenNotSentException
     */
    public function isValidApiRequest(): true
    {
        if ($this->rpcConfig->securityConfig->protectedApi) {
            $this->getValidator()->isValid($this->getTokenHolder()->getToken());
        }
        return true;
    }

    public function getTokenHolder(): IRpcTokenHolder
    {
        return $this->holder ?? throw new RpcTokenNotSentException();
    }

    private function getValidator(): ITokenValidator
    {
        return $this->validatorRegistry?->getValidator(
            $this->apiGroupContext?->getGroup() ?? APIGroups::DEFAULT_GROUP,
            $this->tokenValidator
        ) ?? $this->tokenValidator;
    }

}