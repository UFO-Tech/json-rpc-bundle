<?php
/**
 * @author Doctor <doctor.netpeak@gmail.com>
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
     * @return bool
     */
    public function isProtectedGet();

    /**
     * @return bool
     */
    public function isProtectedPost();

    /**
     * @return string
     */
    public function getTokenHeader();

    /**
     * @param $token
     * @return bool
     * @throws InvalidTokenException
     */
    public function isValidToken($token);

    /**
     * @return bool
     * @throws InvalidTokenException
     * @throws TokenNotFoundInHeaderException
     */
    public function isValidGetRequest();

    /**
     * @return bool
     * @throws InvalidTokenException
     * @throws TokenNotFoundInHeaderException
     */
    public function isValidPostRequest();

}