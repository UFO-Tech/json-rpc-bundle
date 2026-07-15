<?php

namespace Ufo\JsonRpcBundle\ParamConvertors;

use BackedEnum;
use RuntimeException;
use Throwable;
use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\DTO\Transformer\Converter\EnumConverter;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\RpcObject\RPC\Param;
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
        $fqcn = $context[TypeHintResolver::CLASS_FQCN] ?? throw new RuntimeException('classFQCN is required in context');

        if (!is_subclass_of($fqcn, UnitEnum::class)) {
            throw new RuntimeException("{$fqcn} is not a UnitEnum or BackedEnum");
        }

        try {
            $enum = EnumConverter::toEnum($fqcn, $value);
        } catch (Throwable) {
            $enum = is_subclass_of($fqcn, BackedEnum::class) ? $fqcn::tryFrom($value) : null;
        }

        if (!$enum && method_exists($fqcn, 'tryFromValue')) {
            try {
                $enum = $fqcn::tryFromValue($value);
            } catch (Throwable) {}
        }

        if (!$enum) {
            /** @var ParamDefinition $paramDef */
            if (
                !($paramDef = $context['paramDefinition'] ?? false)
                || !$paramDef->isOptional() || $paramDef->getDefault() !== null
            ) {
                throw new RuntimeException("Invalid value '{$value}' for enum {$fqcn}");
            }
            return null;
        }

        if ($callback !== null) {
            $enum = $callback($value, $enum);
        }

        return $enum;
    }

    public function supported(string $classFQCN): bool
    {
        return is_subclass_of($classFQCN, UnitEnum::class) && !is_subclass_of($classFQCN, BackedEnum::class);
    }

    public function getParamAttr(string $classFQCN): Param
    {
        $ref = new \ReflectionEnum($classFQCN);
        return new Param(Param::STRING, context: [Param::C_CONVERTOR => $this::class]);
    }
}
