<?php

namespace Ufo\JsonRpcBundle;

use Ufo\Packages\UfoPackage;

use const PHP_EOL;

final class Package extends UfoPackage
{
    const string SPECIFICATION = 'https://www.jsonrpc.org/specification';

    public static function protocolSpecification(): string
    {
        return Package::SPECIFICATION;
    }

    public static function description(): string
    {
        $self = static::getInstance(static::class);
        $self->description = 'Json-RPC api server from UFO-Tech';
        $self->description .= PHP_EOL.PHP_EOL;
        $self->description .= UfoPackage::UFO_DESCRIPTION;

        return parent::description();
    }

}
