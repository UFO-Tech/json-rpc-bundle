<?php
/**
 * @author Doctor <ashterix69@gmail.com>
 *
 *
 * Date: 21.04.2017
 * Time: 10:19
 */

namespace Ufo\JsonRpcBundle\Exceptions;


class TokenNotFoundInHeaderException extends \Exception
{
    protected $message = 'Token not found in header';
}