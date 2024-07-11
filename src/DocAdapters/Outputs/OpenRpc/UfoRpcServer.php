<?php

namespace Ufo\JsonRpcBundle\DocAdapters\Outputs\OpenRpc;

use PSX\OpenAPI\Server;
use PSX\Record\RecordInterface;
use Ufo\JsonRpcBundle\Package;

class UfoRpcServer extends Server
{

    const string NAME = 'UFO Json-RPC Server';

    public function __construct(
        protected string $envelop,
        protected string $name = self::NAME,
        protected array $transports = []
    ) {}

    public function toRecord(): RecordInterface
    {
        $this->description = 'Json-RPC api server from UFO Tec

UFO Tech, or Universal Flexible Open Technologies, is an initiative aimed at providing PHP developers with tools to create complex yet user-friendly solutions for modern web applications and service-oriented architectures.';
        $record = parent::toRecord();
        $record->put('name', $this->name . ' v.' . Package::version());
        $record->put('x-method', "POST");
        $record->put('x-ufo', [
            'envelop' => $this->envelop,
            'transport' => $this->transports,
            'documentation' =>  [
                'json-rpc' => Package::protocolSpecification(),
                Package::bundleName() => Package::bundleDocumentation(),
            ],

        ]);
        return $record;
    }

}