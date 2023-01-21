<?php
/**
 * @author Doctor <ashterix69@gmail.com>
 *
 *
 * Date: 21.04.2017
 * Time: 10:19
 */

namespace Ufo\JsonRpcBundle\Exceptions;


class InvalidTokenException extends \Exception
{
    protected $message = 'Invalid token';
}