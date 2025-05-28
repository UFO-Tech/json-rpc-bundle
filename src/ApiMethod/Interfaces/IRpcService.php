<?php

namespace Ufo\JsonRpcBundle\ApiMethod\Interfaces;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(IRpcService::TAG)]
interface IRpcService
{
    const string TAG = 'ufo.rpc.service';
    const true INIT = true;
}
