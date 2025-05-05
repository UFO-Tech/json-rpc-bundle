<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use TypeError;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Genearator;
use Ufo\RpcObject\Helpers\TypeHintResolver;
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
        private readonly Genearator $generator
    ) {}

    /**
     * @param Assertions $data
     * @param ?string $format
     * @param array $context
     * @return array
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $type = $context['type'] ?? '';
        $this->checkTheType($type);
        foreach ($data->assertions as $assertion) {
            $this->generator->dispatch($assertion, $this->schema);
        }
        $schema = $this->schema;
        $this->schema = [];

        return $schema;
    }

    protected function checkTheType(array|string $type): void
    {
        try {
            if (is_array($type)) {
                $this->schema['oneOf'] = $this->convertArrayOfTypes($type);
            } else {
                $this->schema = $this->convertSingleType($type);
            }
        } catch (InvalidArgumentException) {
            $this->schema['oneOf'] = TypeHintResolver::mixedForJsonSchema();
        }
    }

    public function convertSingleType(string $type): array
    {
        if (empty($type)) throw new InvalidArgumentException('Parameter type is empty');
        $result = TypeHintResolver::phpToJsonSchema($type);
        return [TypeHintResolver::TYPE => $result];
    }

    protected function convertArrayOfTypes(array $types): array
    {
        $result = [];
        foreach ($types as $type) {
            $result[] = $this->convertSingleType($type);
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