<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\EnumProcessor;

use ReflectionException;
use Ufo\DTO\Helpers\EnumResolver;

class EnumsHolder
{
    /**
     * @var array<EnumDefinition>
     */
    protected array $enums = [];

    /**
     * @return EnumDefinition[]
     */
    public function getEnums(): array
    {
        return $this->enums;
    }


    public function getEnum(string $enumFQCN): EnumDefinition
    {
        $enumFQCN = EnumResolver::getEnumFQCN($enumFQCN);

        if (!isset($this->enums[$enumFQCN])) {
            $enumData = EnumResolver::generateEnumSchema($enumFQCN);
            $this->enums[$enumFQCN] = EnumDefinition::fromArray($enumData);
        }

        return $this->enums[$enumFQCN];
    }

}