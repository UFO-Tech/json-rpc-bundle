<?php
/**
 * @author Doctor <doctor.netpeak@gmail.com>
 *
 *
 * Date: 21.04.2017
 * Time: 10:10
 */

namespace Ufo\JsonRpcBundle\Security;


use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\Exceptions\TokenNotFoundInHeaderException;

class Helper
{
    /**
     * @param Request $request
     * @param string $tokenHeaderKey
     * @return string
     * @throws TokenNotFoundInHeaderException
     */
    public static function tokenFromRequest(Request $request, $tokenHeaderKey)
    {
        $token = $request->headers->get($tokenHeaderKey);
        if (is_null($token)) {
            throw new TokenNotFoundInHeaderException();
        }
        return $token;
    }
}