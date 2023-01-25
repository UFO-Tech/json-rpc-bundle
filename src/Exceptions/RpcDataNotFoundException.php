<?php
/**
 * @file: NotFoundException.php
 * @author Alex Maystrenko <ashterix69@gmail.com>
 *
 * Class - NotFoundException
 *
 * Created by PhpStorm.
 * Date: 11.07.2016
 * Time: 16:26
 */

namespace Ufo\JsonRpcBundle\Exceptions;


class RpcDataNotFoundException extends AbstractJsonRpcBundleException implements IProcedureExceptionInterface
{
    protected $code = -32000;
}