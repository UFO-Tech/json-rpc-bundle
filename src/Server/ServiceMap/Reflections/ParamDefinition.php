<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections;

use ReflectionAttribute;
use ReflectionParameter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Ufo\DTO\ArrayConstructibleTrait;
use Ufo\DTO\ArrayConvertibleTrait;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\DTO\Interfaces\IArrayConstructible;
use Ufo\DTO\Interfaces\IArrayConvertible;
use Ufo\JsonRpcBundle\Server\ServiceMap\AttributesCollection;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RPC\Param;

use function array_map;
use function class_exists;
use function is_string;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

class ParamDefinition implements IArrayConvertible, IArrayConstructible
{
    use ArrayConvertibleTrait, ArrayConstructibleTrait;

    protected bool $optional = false;
    protected mixed $default = null;
    readonly public AttributesCollection $attributesCollection;

    public function __construct(
        readonly public string $name,
        protected array $type,
        protected string|array $realType,
        readonly public string $description = '',
        protected array $schema = [],
        readonly public ?string $paramItems = null
    )
    {
        $this->attributesCollection = new AttributesCollection();
    }

    public static function fromParamReflection(
        ReflectionParameter $paramRef,
        array $type,
        array|string $realType,
        string $description = '',
        ?string $paramItems = null
    ): static
    {
        $paramDef = new static(
            name: $paramRef->getName(),
            type: $type,
            realType: $realType,
            description: $description,
            schema: $type,
            paramItems: $paramItems
        );
        array_map(function (ReflectionAttribute $attribute) use ($paramDef
        ) {
            try {
                $instance = $attribute->newInstance();
            } catch (LogicException $e) {
                if ($attribute->name !== Autowire::class) throw $e;
                $instance = new Autowire('');
            }

            return $paramDef->attributesCollection->addAttribute($instance);
        }, $paramRef->getAttributes());
        return $paramDef;
    }

    public function changeType(array $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function setDefault(mixed $default): static
    {
        if (
            $default === null
            && !$this->optional
            && !(($type = $this->realType) && is_string($type) && $type === TypeHintResolver::MIXED->value)
            && (
                (is_string($this->realType)
                    && !str_starts_with($this->realType, '?')
                    && !str_contains($this->realType, TypeHintResolver::NULL->value))
                || (is_array($this->realType) && !in_array(TypeHintResolver::NULL->value, $this->realType, true))
            )
        ) return $this;

        $this->default = $default;
        $this->optional = true;
        return $this;
    }

    public function isOptional(): bool
    {
        return $this->optional;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function setSchema(array $schema): static
    {
        $this->schema = $schema;

        return $this;
    }

    public function getSchema(): array
    {
        return $this->schema;
    }

    public function getAttributesCollection(): AttributesCollection
    {
        return $this->attributesCollection;
    }

    public function getType(): array|string
    {
        return $this->type;
    }

    public function getRealType(): array|string
    {
        return $this->realType;
    }

}
