<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema;


use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Ufo\DTO\Helpers\EnumResolver;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\DtoReflector;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\DTO\Helpers\TypeHintResolver as T;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcObject\RPC\Assertions;
use Ufo\RpcObject\RPC\AssertionsCollection;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RPC\DTO;
use Ufo\RpcObject\RPC\Param;
use function array_diff_key;

#[AutoconfigureTag('serializer.normalizer')]
class JsonSchemaNormalizer implements NormalizerInterface
{
    const HEADER = [
        '$schema' => 'http://json-schema.org/draft-07/schema#',
        'type'    => 'object',
    ];

    public function __construct(
        private readonly JsonSchemaPropertyNormalizer $paramNormalizer,
        private readonly ChainParamConvertor          $paramConvertor,
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
        foreach ($service->getParams() as $property => $paramDefinition) {

            $this->checkParamConvertor($paramDefinition, $service);

            if ($paramDefinition->getSchema()) continue;

            $assertions = $assertionsList[$property] ?? new Assertions([]);

            $schema = $this->paramNormalizer->normalize(
                $assertions,
                context: [
                    'realType' => $paramDefinition->paramItems,
                    'type' => $paramDefinition->getRealType(),
                    'service' => $service,

                ]
            );
            $paramDefinition->setSchema($schema);
            $properties[$property] = $schema;
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

    /**
     * @throws RpcInternalException
     */
    protected function checkParamConvertor(ParamDefinition $paramDefinition, Service $service): void
    {
        /**
         * @var Param $paramAttr
         */
        $paramAttr = $paramDefinition->getAttributesCollection()->getAttribute(Param::class);
        $assertionsAttr = $paramDefinition->getAttributesCollection()->getAttribute(Assertions::class);

        if ($paramAttr) {
            $assertionsAttr = $assertionsAttr ?? new Assertions([]);

            if ($paramAttr->isCollection()) return;

            $paramDefinition->setSchema(
                $this->paramNormalizer->normalize(
                    $assertionsAttr,
                    context: [
                        'realType' => $paramAttr->getType(),
                        'type' => $paramDefinition->getRealType(),
                        'currentSchema' => $paramDefinition->getType(),
                        'service' => $service,
                        'targetType' => $this->getScalarTargetType($paramAttr->getType()),
                    ]
                )
            )->setDefault($paramAttr->default);

            $attrType = $paramAttr->getType();
            if (is_array($attrType)) {
                $attrType = implode('|', $attrType);
            }

            if (!EnumResolver::schemaHasEnum($paramDefinition->getType())) {
                $paramDefinition = $paramDefinition->changeType(
                    T::typeDescriptionToJsonSchema($attrType, $service->uses),
                );
            }

        }
        $this->checkDTO($paramDefinition->getRealType(), $paramDefinition, $service);
    }

    protected function getScalarTargetType(array|string $type): ?string
    {
        $res = null;
        $type = (is_string($type)) ? [$type] : $type;
        foreach ($type as $t) {
            if (in_array($t, Param::TYPE_HINTS)) {
                $res = $t;
                break;
            }
        }
        return $res;
    }

    /**
     * @throws RpcInternalException
     */
    protected function checkDTO(string|array $type, ParamDefinition $paramDefinition, Service $service): void
    {
        if (is_array($type)) {
            array_map(fn(string $type) => $this->checkDTO($type, $paramDefinition, $service), $type);
            return;
        }
        $nType = T::normalize($type);

        if ($nType === T::ARRAY->value) {
            $tmpSchema = T::typeDescriptionToJsonSchema(
                $paramDefinition->paramItems ?? $type,
                $service->uses
            );

            T::filterSchema($tmpSchema, function (array $schema) use ($service, $paramDefinition) {
                if (($schema[T::TYPE] ?? null) === T::OBJECT->value
                    && class_exists($schema['classFQCN'] ?? null)
                    && !enum_exists($schema['classFQCN'] ?? null)
                ) {
                    $service->addParamsDto($paramDefinition->name, new DtoReflector(
                            new DTO($schema['classFQCN'], context: [DTO::C_COLLECTION => true]
                            ), $this->paramConvertor)
                    );
                }
            });
        }

        if ($nType === T::OBJECT->value && class_exists($type) && !enum_exists($type)) {
            $service->addParamsDto($paramDefinition->name, new DtoReflector(
                    new DTO($type), $this->paramConvertor)
            );
        }
    }
}