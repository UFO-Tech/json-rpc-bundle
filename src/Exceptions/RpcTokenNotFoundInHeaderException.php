<?php
/**
 * @author Doctor <ashterix69@gmail.com>
 *
 *
 * Date: 21.04.2017
 * Time: 10:19
 */

namespace Ufo\JsonRpcBundle\Exceptions;


class RpcTokenNotFoundInHeaderException extends AbstractJsonRpcBundleException implements ISecurityExceptionInterface
{
    protected $code = -32401;
    protected $message = 'Unauthorized. Token not found in header';
}