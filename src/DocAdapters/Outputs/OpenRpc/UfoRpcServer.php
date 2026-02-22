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
        protected array $transports = [],
        protected array $rpcEnv = []
    ) {}

    public function toRecord(): RecordInterface
    {
        $this->description = Package::description();
        $record = parent::toRecord();
        $record->put('name', $this->name . ' v.' . Package::version());
        $record->put('x-method', "POST");
        $record->put('x-ufo', [
            'envelop' => $this->envelop,
            'environment' => $this->rpcEnv,
            'transport' => $this->transports,
            'documentation' =>  [
                'postman' => $this->url . '/postman',
                'json-rpc' => Package::protocolSpecification(),
                Package::bundleName() => Package::bundleDocumentation(),
            ],

        ]);
        return $record;
    }

}