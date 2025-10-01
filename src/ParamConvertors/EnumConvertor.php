<?php

namespace Ufo\JsonRpcBundle\ParamConvertors;

use BackedEnum;
use RuntimeException;
use Ufo\DTO\DTOTransformer;
use UnitEnum;

class EnumConvertor implements IParamConvertor
{
    public function toScalar(object $object, array $context = [], ?callable $callback = null): string|int|float|null
    {
        if (!$object instanceof UnitEnum) {
            throw new RuntimeException('Expected Enum, got ' . get_debug_type($object));
        }

        $value = $object instanceof BackedEnum ? $object->value : $object->name;

        if ($callback !== null) {
            $value = $callback($value, $object);
        }

        return $value;
    }

    public function toObject(int|string|float|null $value, array $context = [], ?callable $callback = null): ?object
    {
        $fqcn = $context['classFQCN'] ?? throw new RuntimeException('classFQCN is required in context');

        if (!is_subclass_of($fqcn, UnitEnum::class)) {
            throw new RuntimeException("{$fqcn} is not a UnitEnum or BackedEnum");
        }

        try {
            $enum = DTOTransformer::transformEnum($fqcn, $value);
            if (!$enum instanceof UnitEnum) {
                $enum = null;
            }
        } catch (\Throwable) {
            $enum = is_subclass_of($fqcn, BackedEnum::class) ? $fqcn::tryFrom($value) : null;
        }

        if (!$enum && method_exists($fqcn, 'tryFromValue')) {
            try {
                $enum = $fqcn::tryFromValue($value);
            } catch (\Throwable) {}
        }

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