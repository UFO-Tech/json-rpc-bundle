<?php

namespace Ufo\JsonRpcBundle\ParamConvertors;

use BackedEnum;
use ReflectionException;
use RuntimeException;
use Throwable;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\RpcObject\RPC\Param;

use function is_subclass_of;

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
        $fqcn = $context[TypeHintResolver::CLASS_FQCN] ?? throw new RuntimeException('classFQCN is required in context');

        if (!is_subclass_of($fqcn, BackedEnum::class)) {
            throw new RuntimeException("{$fqcn} is not a BackedEnum");
        }

        $enum = $fqcn::tryFrom($value);

        if (!$enum && method_exists($fqcn, 'tryFromValue')) {
            try {
                $enum = $fqcn::tryFromValue($value);
            } catch (Throwable) {}
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

    public function getParamAttr(string $classFQCN): Param
    {
        $ref = new \ReflectionEnum($classFQCN);
        return new Param(Param::bitFromType($ref->getBackingType()->getName()), context: [Param::C_CONVERTOR => $this::class]);
    }
}
