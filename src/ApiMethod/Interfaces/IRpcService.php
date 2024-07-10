<?php
/**
 * Created by PhpStorm.
 * User: ashterix
 * Date: 26.09.16
 * Time: 19:25
 */

namespace Ufo\JsonRpcBundle\ApiMethod\Interfaces;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('ufo.rpc.service')]
interface IRpcService
{
    const true INIT = true;
}
