<?php

namespace Ufo\JsonRpcBundle\DocAdapters\Outputs\OpenRpc;

use PSX\OpenRPC\ContentDescriptor;
use PSX\Record\RecordInterface;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\RpcObject\DocsHelper\XUfoValuesEnum;

use function is_null;

class UfoRpcParameter extends ContentDescriptor
{

    public function __construct(
        protected ?string $assertions = null
    ) {}

    public function toRecord(): RecordInterface
    {
        $record = parent::toRecord();
        if (!is_null($this->assertions)) {
            $record->put(XUfoValuesEnum::ASSERTIONS->value, $this->assertions);
        }
        return $record;
    }
}