<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
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
     * @param Assertions $assertions
     * @param ?string $format
     * @param array $context
     * @return array
     */
    public function normalize($assertions, ?string $format = null, array $context = []): array
    {
        $this->checkTheType($context);
        foreach ($assertions->assertions as $assertion) {
            $this->generator->dispatch($assertion, $this->schema);
        }
        $schema = $this->schema;
        $this->schema = [];

        return $schema;
    }

    protected function checkTheType(array $context): void
    {
        $result = TypeHintResolver::phpToJsonSchema($context['type'] ?? '');
        if (empty($result)) {
            $result = TypeHintResolver::mixedForJsonSchema();
        }

        if (is_array($result)) {
            $this->schema['oneOf'] = $result;
        } else {
            $this->schema[TypeHintResolver::TYPE] = $result;
        }
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