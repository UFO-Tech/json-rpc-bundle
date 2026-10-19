<?php

namespace Ufo\JsonRpcBundle\ParamConvertors;

use BackedEnum;
use ReflectionEnum;
use ReflectionException;
use RuntimeException;
use Throwable;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcObject\RPC\Param;

use function array_map;
use function implode;
use function is_int;
use function is_string;
use function is_subclass_of;
use function preg_match;
use function sprintf;

class BackedEnumConvertor implements INullableParamConvertor
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

        $enum = $this->fromBackingValue($fqcn, $value) ?? $this->fromAlias($fqcn, $value);

        return match (true) {
            $enum !== null => $callback !== null ? $callback($value, $enum) : $enum,
            $value === null && $this->acceptsNull($context) => null,
            default => throw new RpcBadParamException(sprintf(
                'Invalid value "%s", expected one of: %s',
                $value,
                implode(', ', array_map(static fn (BackedEnum $case): string => (string) $case->value, $fqcn::cases()))
            )),
        };
    }

    /**
     * @param class-string<BackedEnum> $fqcn
     */
    protected function fromAlias(string $fqcn, int|string|float|null $value): ?BackedEnum
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

    /**
     * @param class-string<BackedEnum> $fqcn
     * @throws ReflectionException
     */
    protected function fromBackingValue(string $fqcn, int|string|float|null $value): ?BackedEnum
    {
        $backingType = (new ReflectionEnum($fqcn))->getBackingType()?->getName();
        $value = match (true) {
            $backingType === 'int' && is_int($value) => $value,
            $backingType === 'int' && is_string($value) && preg_match('/^-?\d+$/', $value) === 1 => (int) $value,
            $backingType === 'string' && (is_string($value) || is_int($value)) => (string) $value,
            default => null,
        };

        return $value === null ? null : $fqcn::tryFrom($value);
    }

    public function supported(string $classFQCN): bool
    {
        return is_subclass_of($classFQCN, BackedEnum::class);
    }

    public function getParamAttr(string $classFQCN, ): Param
    {
        $ref = new \ReflectionEnum($classFQCN);
        return new Param(Param::bitFromType($ref->getBackingType()->getName()), context: [Param::C_CONVERTOR => $this::class]);
    }

    public function getNullableParamAttr(string $classFQCN, ParamDefinition $param): Param
    {
        $ref = new \ReflectionEnum($classFQCN);
        $bits = Param::bitFromType($ref->getBackingType()->getName());
        if ($param->acceptsNull()) {
            $bits |= Param::NULL;
        }
        return new Param($bits, context: [Param::C_CONVERTOR => $this::class]);
    }
}
