<?php

namespace Ufo\JsonRpcBundle\ParamConvertors;

use BackedEnum;
use RuntimeException;

class BackedEnumConvertor implements IParamConvertor
{
    public function toScalar(object $object, array $context = [], ?callable $callback = null): string|int|float|null
    {
        if (!$object instanceof BackedEnum) {
            throw new RuntimeException('Expected BackedEnum, got ' . get_debug_type($object));
        }

        $value = $object->value;

        if ($callback !== null) {
            $value = $callback($value, $object);
        }

        return $value;
    }

    public function toObject(int|string|float|null $value, array $context = [], ?callable $callback = null): ?object
    {
        $fqcn = $context['classFQCN'] ?? throw new RuntimeException('classFQCN is required in context');

        if (!is_subclass_of($fqcn, BackedEnum::class)) {
            throw new RuntimeException("{$fqcn} is not a BackedEnum");
        }

        $enum = $fqcn::tryFrom($value);
        if (!$enum) {
            throw new RuntimeException("Invalid value '{$value}' for enum {$fqcn}");
        }

        if ($callback !== null) {
            $enum = $callback($value, $enum);
        }

        return $enum;
    }

    public function supported(string $classFQCN): bool
    {
        return is_subclass_of($classFQCN, BackedEnum::class);
    }
}