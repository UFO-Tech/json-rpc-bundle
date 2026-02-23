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

    /**
     * @param class-string $enumFQCN
     * @return EnumDefinition
     * @throws ReflectionException
     */
    public function getEnum(string $enumFQCN): EnumDefinition
    {
        if (!$definition = $this->enums[$enumFQCN] ?? false) {
            $enumFQCN = EnumResolver::getEnumFQCN($enumFQCN);
            $enumData = EnumResolver::generateEnumSchema($enumFQCN);
            $this->enums[$enumFQCN] = EnumDefinition::fromArray($enumData);
        }
        return $definition;
    }

}