<?php

namespace Ufo\JsonRpcBundle\ParamConvertors;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\Validations\JsonSchema\JsonSchemaPropertyNormalizer;

class ChainParamConvertor
{

    /**
     * @param IParamConvertor[] $convertors
     */
    public function __construct(
        #[AutowireIterator(IParamConvertor::TAG)]
        protected iterable $convertors = [],
        readonly public JsonSchemaPropertyNormalizer $jsonSchemaPropertyNormalizer
    ) {}

    public function toScalar(object $object, array $context = [], ?callable $callback = null): string|int|float|null
    {
        foreach ($this->convertors as $convertor) {
            if (!$convertor->supported($object::class)) continue;
            return $convertor->toScalar($object, $context, $callback);
        }
        throw new \RuntimeException('No convertor found for ' . $object::class);
    }

    public function toObject(float|int|string|null $value, array $context = [], ?callable $callback = null): ?object
    {
        $classFQCN = $context[TypeHintResolver::CLASS_FQCN] ?? throw new \RuntimeException('No "classFQCN" provided to convertor context');
        $needConvertor = $context['param']?->convertorFQCN ?? null;
        foreach ($this->convertors as $convertor) {
            if ($needConvertor && $convertor::class !== $needConvertor) continue;

            if (!$convertor->supported($classFQCN)) continue;
            return $convertor->toObject($value, $context, $callback);
        }
        throw new \RuntimeException('No convertor found for ' . $value);
    }
}