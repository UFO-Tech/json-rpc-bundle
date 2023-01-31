<?php
/**
 * @author Doctor <ashterix69@gmail.com>
 *
 *
 * Date: 21.04.2017
 * Time: 10:10
 */

namespace Ufo\JsonRpcBundle\Security;


use Symfony\Component\HttpFoundation\Request;
use Ufo\RpcError\RpcTokenNotFoundInHeaderException;

class Helper
{
    /**
     * @param Request $request
     * @param string $tokenHeaderKey
     * @return string
     * @throws RpcTokenNotFoundInHeaderException
     */
    public static function tokenFromRequest(Request $request, string $tokenHeaderKey): string
    {
        $token = $request->headers->get($tokenHeaderKey);
        if (is_null($token)) {
            throw new RpcTokenNotFoundInHeaderException();
        }
        return $token;
    }
}