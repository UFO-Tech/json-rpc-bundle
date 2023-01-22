<?php
/**
 * @author Doctor <ashterix69@gmail.com>
 *
 *
 * Date: 21.04.2017
 * Time: 9:23
 */

namespace Ufo\JsonRpcBundle\Security\Interfaces;


use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\Exceptions\InvalidTokenException;
use Ufo\JsonRpcBundle\Exceptions\TokenNotFoundInHeaderException;

interface IRpcSecurity
{
    /**
     * @return string
     */
    public function getTokenHeader(): string;

    /**
     * @param $token
     * @return bool
     * @throws InvalidTokenException
     */
    public function isValidToken($token): bool;

    /**
     * @return bool
     * @throws InvalidTokenException
     * @throws TokenNotFoundInHeaderException
     */
    public function isValidRequest(): bool;

}
