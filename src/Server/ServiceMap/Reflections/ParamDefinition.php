<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections;

use ReflectionAttribute;
use ReflectionParameter;
use Ufo\DTO\ArrayConstructibleTrait;
use Ufo\DTO\ArrayConvertibleTrait;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\DTO\Interfaces\IArrayConstructible;
use Ufo\DTO\Interfaces\IArrayConvertible;
use Ufo\JsonRpcBundle\Server\ServiceMap\AttributesCollection;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;

use function array_map;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

class ParamDefinition implements IArrayConvertible, IArrayConstructible
{
    use ArrayConvertibleTrait, ArrayConstructibleTrait;

    protected string|array $type;
    protected bool $optional = false;
    protected mixed $default = null;
    protected string|array $realType;
    protected AttributesCollection $attributesCollection;

    public function __construct(
        readonly public string $name,
        string|array $type,
        readonly public string $description = '',
        protected array $schema = [],
        readonly public ?string $paramItems = null
    )
    {
        $this->type = Service::validateParamType($type);
        $this->realType = $type;
        $this->attributesCollection = new AttributesCollection();
    }

    public static function fromParamReflection(
        ReflectionParameter $paramRef,
        string|array $type, 
        string $description = '',
        ?string $paramItems = null
    ): static
    {
        $paramDef = new static(
            $paramRef->getName(),
            $type,
            $description,
            paramItems: $paramItems
        );
        array_map(fn(ReflectionAttribute $attribute) => $paramDef->attributesCollection->addAttribute($attribute->newInstance()), $paramRef->getAttributes());
        return $paramDef;
    }

    public function changeType(string|array $type): static
    {
        $this->type = Service::validateParamType($type);
        return $this;
    }

    public function setDefault(mixed $default): static
    {
        if (
            $default === null
            && !$this->optional
            && (
                (is_string($this->realType) && !str_contains($this->realType, TypeHintResolver::NULL->value))
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
