<?php
/**
 * Responsible for converting DateTime objects to scalar formats and vice versa
 */

namespace Ufo\JsonRpcBundle\ParamConvertors;

use Ramsey\Uuid\Rfc4122;
use RuntimeException;
use Symfony\Component\Uid;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Stringable;
use Throwable;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcObject\RPC\Param;

use function class_exists;
use function in_array;

class UuidConverter implements INullableParamConvertor
{
    protected const array SUPPORTS_MAP = [
        Uid\AbstractUid::class,
        Uid\Uuid::class,
        Uid\UuidV1::class,
        Uid\UuidV3::class,
        Uid\UuidV4::class,
        Uid\UuidV5::class,
        Uid\UuidV6::class,
        Uid\UuidV7::class,
        Uid\UuidV8::class,
        UuidInterface::class,
        Uuid::class,
        Rfc4122\UuidV1::class,
        Rfc4122\UuidV2::class,
        Rfc4122\UuidV3::class,
        Rfc4122\UuidV4::class,
        Rfc4122\UuidV5::class,
        Rfc4122\UuidV6::class,
        Rfc4122\UuidV7::class,
        Rfc4122\UuidV8::class,
    ];

    /**
     * Convert a DateTime object to scalar format
     * @param object $object DateTime object to convert
     * @param array<string,mixed> $context context: ['format' => 'Y-m-d H:i:s']
     * @param callable|null $callback Optional callback to modify result $callback($scalar, $object)
     * @return string|int|float|null Formatted date string or null
     */
    public function toScalar(object $object, array $context = [], ?callable $callback = null): string|int|float|null
    {
        /**
         * @var Stringable $object
         */
        try {
            return $object->toString();
        } catch (Throwable) {
            try {
                return (string)$object;
            } catch (Throwable) {
                throw new RuntimeException('No convertor found for ' . $object::class);
            }
        }
    }

    public function toObject(int|string|float|null $value, array $context = [], ?callable $callback = null): ?object
    {
        if ($value === null && ($context['paramDefinition'] ?? null) instanceof ParamDefinition && $context['paramDefinition']->acceptsNull()) {
            return null;
        }
        $classFQCN = $context[TypeHintResolver::CLASS_FQCN] ?? null;
        $factory = match (true) {
            in_array($classFQCN, static::SUPPORTS_MAP) && class_exists($classFQCN) => $classFQCN::fromString(...),
            $classFQCN === UuidInterface::class || $classFQCN === Uid\AbstractUid::class => Uuid::fromString(...),
            default => throw new RuntimeException('No convertor found for "' . $value . '" and classFQCN: ' . $classFQCN),
        };
        try {
            return $factory((string) $value);
        } catch (Throwable) {
            throw new RpcBadParamException('Invalid value "' . $value . '", expected a UUID');
        }
    }

    public function supported(string $classFQCN): bool
    {
        return in_array($classFQCN, static::SUPPORTS_MAP);
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