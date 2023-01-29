<?php

namespace Ufo\JsonRpcBundle\RpcCallback\Validator;

use Symfony\Component\Validator\Constraints\Url;

#[\Attribute]
class AsertRealUrl extends Url
{
    public $message = 'This value is not a valid URL.';

    public function validatedBy()
    {
        return RealUrlValidator::class;
    }
}
