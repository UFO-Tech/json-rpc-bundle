<?php

namespace Ufo\JsonRpcBundle\ParamConvertors;

use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\RpcObject\RPC\Param;

interface INullableParamConvertor extends IParamConvertor
{
    public function getNullableParamAttr(string $classFQCN, ParamDefinition $param): Param;
}
