<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Generator;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\RpcObject\RPC\Assertions;

use function is_array;

#[AutoconfigureTag('serializer.normalizer')]
class JsonSchemaPropertyNormalizer implements NormalizerInterface
{
    /**
     * @var array|mixed
     */
    protected array $schema = [];

    public function __construct(
        private readonly Generator $generator
    ) {}

    /**
     * @param Assertions $data
     * @param ?string $format
     * @param array $context
     * @return array
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $this->schema = [];
        $type = $context['realType'] ?? $context['type'] ?? '';
        $this->checkTheType($type, ($context['service']?->uses) ?? []);

        $targetType = $context['targetType'] ?? TypeHintResolver::ANY->value;

        return TypeHintResolver::applyToSchema(
            $this->schema,
            function (array $itemSchema) use ($data, $context, $targetType) {
                $type = ($itemSchema[TypeHintResolver::TYPE] ?? false);
                if ($type && ($targetType === $type || $targetType === TypeHintResolver::ANY->value)) {
                    foreach ($data->assertions as $assertion) {
                        $this->generator->dispatch($assertion, $itemSchema);
                    }
                }
                return $itemSchema;
            }
        );
    }

    protected function checkTheType(array|string $type, array $classes = []): void
    {
        if (is_array($type)) {
            $type = implode('|', $type);
        }
        $this->schema = TypeHintResolver::typeDescriptionToJsonSchema($type, $classes);
    }

    public function convertSingleType(string $type, array $classes = []): array
    {
        if (empty($type)) throw new InvalidArgumentException('Parameter type is empty');
        return TypeHintResolver::typeDescriptionToJsonSchema($type, $classes);
    }

    protected function convertArrayOfTypes(array $types, array $classes = []): array
    {
        $result = [];
        foreach ($types as $type) {
            $result[] = $this->convertSingleType($type, $classes);
        }
        return $result;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Assertions;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Assertions::class => true,
        ];
    }
}