<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema;


use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Ufo\RpcObject\RPC\Assertions;
use Ufo\RpcObject\RPC\AssertionsCollection;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use function array_diff_key;

#[AutoconfigureTag('serializer.normalizer')]
class JsonSchemaNormalizer implements NormalizerInterface
{
    const HEADER = [
        '$schema' => 'http://json-schema.org/draft-07/schema#',
        'type'    => 'object',
    ];

    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer,
        private readonly JsonSchemaPropertyNormalizer $paramNormalizer,
    ) {}

    /**
     * @param AssertionsCollection $data
     * @param ?string $format
     * @param array $context
     * @return array
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        /**
         * @var ?Service $service
         */
        $service = $context['service'] ?? null;

        $assertionsList = $data->getAssertionsCollection();
        $properties = [];
        foreach ($service->getParams() as $property => $data) {
            $assertions = $assertionsList[$property] ?? new Assertions([]);
            $properties[$property] = $this->paramNormalizer->normalize(
                $assertions,
                context: $data
            );
        }
        return static::HEADER + [
                'properties' => $properties,
                'required'   => array_keys(array_diff_key($properties, $service->getDefaultParams())),
            ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof AssertionsCollection;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            AssertionsCollection::class => true,
        ];
    }
}