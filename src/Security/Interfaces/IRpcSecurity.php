<?php
namespace Ufo\JsonRpcBundle\Security\Interfaces;

use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcTokenNotSentException;

interface IRpcSecurity
{

    public function setTokenHolder(IRpcTokenHolder $holder): self;

    /**
     * @throws RpcTokenNotSentException
     * @return IRpcTokenHolder
     */
    public function getTokenHolder(): IRpcTokenHolder;

    /**
     * @return bool
     * @throws RpcInvalidTokenException
     * @throws RpcTokenNotSentException
     */
    public function isValidDocRequest(): true;

    /**
     * @return bool
     * @throws RpcInvalidTokenException
     * @throws RpcTokenNotSentException
     */
    public function isValidApiRequest(): true;
}
