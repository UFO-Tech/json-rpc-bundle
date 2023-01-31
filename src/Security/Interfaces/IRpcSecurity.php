<?php
/**
 * @author Doctor <ashterix69@gmail.com>
 *
 *
 * Date: 21.04.2017
 * Time: 9:23
 */

namespace Ufo\JsonRpcBundle\Security\Interfaces;



use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcTokenNotFoundInHeaderException;

interface IRpcSecurity
{
    /**
     * @return string
     */
    public function getTokenHeaderKey(): string;

   /**
     * @return string
     */
    public function getToken(): string;

    /**
     * @param $token
     * @return bool
     * @throws RpcInvalidTokenException
     */
    public function isValidToken($token): bool;

    /**
     * @return bool
     * @throws RpcInvalidTokenException
     * @throws RpcTokenNotFoundInHeaderException
     */
    public function isValidRequest(): bool;

}
