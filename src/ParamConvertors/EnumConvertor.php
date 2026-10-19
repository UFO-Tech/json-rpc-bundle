<?php

namespace Ufo\JsonRpcBundle\ParamConvertors;

use BackedEnum;
use RuntimeException;
use Throwable;
use TypeError;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\DTO\Transformer\Converter\EnumConverter;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcObject\RPC\Param;
use UnitEnum;

use function array_map;
use function implode;
use function sprintf;

class EnumConvertor implements INullableParamConvertor
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

    /**
     * @throws RpcBadParamException
     */
    public function toObject(int|string|float|null $value, array $context = [], ?callable $callback = null): ?object
    {
        $fqcn = $context[TypeHintResolver::CLASS_FQCN] ?? throw new RuntimeException('classFQCN is required in context');

        if (!is_subclass_of($fqcn, UnitEnum::class)) {
            throw new RuntimeException("{$fqcn} is not a UnitEnum or BackedEnum");
        }

        $enum = $this->fromName($fqcn, $value) ?? $this->fromAlias($fqcn, $value);

        return match (true) {
            $enum !== null => $callback !== null ? $callback($value, $enum) : $enum,
            $value === null && $this->acceptsNull($context) => null,
            default => throw new RpcBadParamException(sprintf(
                'Invalid value "%s", expected one of: %s',
                $value,
                implode(', ', array_map(
                    static fn (UnitEnum $case): string => $case instanceof BackedEnum ? (string) $case->value : $case->name,
                    $fqcn::cases()
                ))
            )),
        };
    }

    /**
     * @param class-string<UnitEnum> $fqcn
     */
    protected function fromName(string $fqcn, int|string|float|null $value): ?UnitEnum
    {
        try {
            return EnumConverter::toEnum($fqcn, $value);
        } catch (Throwable) {}

        try {
            return is_subclass_of($fqcn, BackedEnum::class) ? $fqcn::tryFrom($value) : null;
        } catch (TypeError) {
            return null;
        }
    }

    /**
     * @param class-string<UnitEnum> $fqcn
     */
    protected function fromAlias(string $fqcn, int|string|float|null $value): ?UnitEnum
    {
        if (!method_exists($fqcn, 'tryFromValue')) {
            return null;
        }
        try {
            return $fqcn::tryFromValue($value);
        } catch (Throwable) {
            return null;
        }
    }

    protected function acceptsNull(array $context): bool
    {
        $paramDef = $context['paramDefinition'] ?? null;
        return $paramDef instanceof ParamDefinition && $paramDef->acceptsNull();
    }

    public function supported(string $classFQCN): bool
    {
        return is_subclass_of($classFQCN, UnitEnum::class) && !is_subclass_of($classFQCN, BackedEnum::class);
    }

    public function getParamAttr(string $classFQCN): Param
    {
        return new Param(Param::STRING, context: [Param::C_CONVERTOR => $this::class]);
    }

    public function getNullableParamAttr(string $classFQCN, ParamDefinition $param): Param
    {
        return new Param(
            $param->acceptsNull() ? Param::NULLABLE_STRING : Param::STRING,
            context: [Param::C_CONVERTOR => $this::class]
        );
    }
}
