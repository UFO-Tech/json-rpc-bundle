<?php
/**
 * Responsible for converting DateTime objects to scalar formats and vice versa
 */

namespace Ufo\JsonRpcBundle\ParamConvertors;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;
use Ufo\DTO\Helpers\TypeHintResolver;
use function in_array;

class DateTimeConverter implements IParamConvertor
{
    protected const array SUPPORTS_MAP = [
        DateTime::class,
        DateTimeImmutable::class,
        DateTimeInterface::class,
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
         * @var DateTimeInterface $object
         */
        $format = $context['format'] ?? 'Y-m-d H:i:s';
        $value = $object->format($format);
        if ($callback !== null) {
            $value = $callback($value, $object);
        }

        return $value;
    }

    public function toObject(int|string|float|null $value, array $context = [], ?callable $callback = null): ?object
    {
        $classFQCN = $context[TypeHintResolver::CLASS_FQCN] ?? null;
        try {
            $object = new $classFQCN($value);
        } catch (Throwable) {
            try {
                $object = new DateTime($value ?? 'now');
            } catch (Throwable) {
                $object = new DateTime();
            }
        }

        if ($callback !== null) {
            $object = $callback($value, $object);
        }
        return $object;
    }

    public function supported(string $classFQCN): bool
    {
        return in_array($classFQCN, static::SUPPORTS_MAP);
    }

}