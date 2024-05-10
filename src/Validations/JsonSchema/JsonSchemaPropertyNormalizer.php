<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Ufo\JsonRpcBundle\Server\ServiceMap\TypeHintResolver;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Genearator;
use Ufo\RpcObject\RPC\Assertions;
use Symfony\Component\Validator\Constraints as Assert;

#[AutoconfigureTag('serializer.normalizer')]
class JsonSchemaPropertyNormalizer implements NormalizerInterface
{
    /**
     * @var array|mixed
     */
    protected array $schema = [];

    public function __construct(
        private readonly Genearator $genearator
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
        foreach ($assertions as $assertion) {
            foreach ($assertion as $rule) {
                $this->genearator->dispatch($rule, $this->schema);
            }
        }
        $schema = $this->schema;
        $this->schema = [];

        return $schema;
    }

    protected function checkTheType(array $context): void
    {
        $result = TypeHintResolver::phpToJsonSchema($context['type'] ?? '');
        if (empty($result)) {
            $this->schema['oneOf'] = TypeHintResolver::mixedForJsonSchema();
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