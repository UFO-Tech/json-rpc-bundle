<?php

namespace Ufo\JsonRpcBundle\DocAdapters\Outputs\OpenRpc;

use PSX\OpenAPI\Server;
use PSX\Record\RecordInterface;

class CustomServer extends Server
{
    protected string $name;

    public function setName(string $name)
    {
        $this->name = $name;
    }

    public function toRecord(): RecordInterface
    {
        $record = parent::toRecord();
        $record->put('name', $this->name);
        return $record;
    }

}