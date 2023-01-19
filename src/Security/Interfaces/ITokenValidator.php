<?php
/**
 * Created by PhpStorm.
 * User: ashterix
 * Date: 30.04.17
 * Time: 8:58
 */

namespace Ufo\JsonRpcBundle\Security\Interfaces;


use Ufo\JsonRpcBundle\Exceptions\InvalidTokenException;

interface ITokenValidator
{
    /**
     * @param string $token
     * @return bool
     * @throws InvalidTokenException
     */
    public function isValid(string $token): bool;
}