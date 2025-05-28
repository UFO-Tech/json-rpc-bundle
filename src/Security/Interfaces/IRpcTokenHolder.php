<?php
namespace Ufo\JsonRpcBundle\Security\Interfaces;

use Ufo\RpcError\RpcTokenNotSentException;

interface IRpcTokenHolder
{
    /**
     * @return string
     */
    public function getTokenKey(): string;

    /**
     * @return string
     * @throws RpcTokenNotSentException
     */
    public function getToken(): string;
}
