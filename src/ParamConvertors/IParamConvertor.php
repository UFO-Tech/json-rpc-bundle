<?php

namespace Ufo\JsonRpcBundle\ParamConvertors;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Ufo\RpcObject\RPC\Param;

#[AutoconfigureTag(IParamConvertor::TAG)]
interface IParamConvertor
{
    const string TAG = 'rpc.param_convertor';

    /**
     * Converts an object to a scalar representation (string, int, float, or null).
     *
     * @param object $object The object to convert.
     * @param array{
     *     param?: Param,   // Optional metadata from the Param attribute.
     *     classFQCN?: class-string,           // Optional fully-qualified class name for validation.
     *     extra?: mixed                       // Optional extra data for custom convertors.
     * } $context Additional context for conversion.
     * @param callable|null $callback Optional callback: function ($scalar, $object): mixed.
     * @return array|string|int|float|null
     */
    public function toScalar(object $object, array $context = [], ?callable $callback = null): array|string|int|float|null;

    /**
     * Converts a scalar value (string, int, float, or null) back to an object.
     *
     * @param int|string|float|null $value The scalar value to convert.
     * @param array{
     *     classFQCN: class-string,            // Required target class for reconstruction.
     *     param?: Param,   // Optional Param attribute with metadata.
     *     nullable?: bool,                    // Whether null is allowed (default false).
     *     default?: mixed,                    // Default value if input is null or invalid.
     *     extra?: mixed                       // Extra parameters specific to a convertor.
     * } $context Additional context for conversion.
     * @param callable|null $callback Optional callback: function ($value, $object): object.
     * @return object|null
     */
    public function toObject(int|string|float|null $value, array $context = [], ?callable $callback = null): ?object;

    public function supported(string $classFQCN): bool;
}