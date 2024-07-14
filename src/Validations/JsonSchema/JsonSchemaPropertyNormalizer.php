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
     * @param Assertions $object
     * @param ?string $format
     * @param array $context
     * @return array
     */
    public function normalize($object, ?string $format = null, array $context = []): array
    {
        $this->checkTheType($context);
        foreach ($object->assertions as $assertion) {
            $this->generator->dispatch($assertion, $this->schema);
        }
        $schema = $this->schema;
        $this->schema = [];

        return $schema;
    }

    protected function checkTheType(array $context): void
    {
        $type = $context['type'] ?? '';
        try {
            $this->schema = $this->convertSingleType($type);
        } catch (InvalidArgumentException) {
            $this->schema['oneOf'] = TypeHintResolver::mixedForJsonSchema();
        } catch (TypeError) {
            $this->schema['oneOf'] = $this->convertArrayOfTypes($type);
        }
    }

    public function convertSingleType(string $type): array
    {
        if (empty($type)) throw new InvalidArgumentException();
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

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
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