<?php

namespace Ufo\JsonRpcBundle\RpcCallback;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CallbackUrlValidator
{
    public static function validate($object, ExecutionContextInterface $context, $payload)
    {
        // somehow you have an array of "fake names"
//        $fakeNames = [/* ... */];

        // check if the name is actually a fake name
//        if (in_array($object->getFirstName(), $fakeNames)) {
//            $context->buildViolation('This name sounds totally fake!')
//                ->atPath('firstName')
//                ->addViolation()
//            ;
//        }
    }
}
