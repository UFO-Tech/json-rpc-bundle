<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use Deprecated;
use ReflectionException;
use TypeError;
use Ufo\DTO\ServiceTransformer;
use Ufo\DTO\SmartArrayConvertibleTrait;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\DtoReflector;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\RpcError\RpcInternalException;
use Ufo\DTO\Interfaces\IArrayConstructible;
use Ufo\DTO\Interfaces\IArrayConvertible;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\RpcObject\RPC\AssertionsCollection;
use Ufo\RpcObject\RPC\DTO;
use Ufo\RpcObject\RPC\Info;
use Ufo\DTO\JsonSerializableTrait;

use function explode;
use function implode;
use function is_array;
use function json_encode;

class Service implements IArrayConvertible, IArrayConstructible
{
    use JsonSerializableTrait, SmartArrayConvertibleTrait;

    protected string $description = '';
    protected ?string $title = null;

    /**
     * @var array<string, ServiceTranslation>
     */
    protected array $translations = [];

    protected array $return = [];

    protected string $returnDescription = '';
    protected ?string $returnItems = null;

    protected ?DTO $responseInfo = null;

    protected array $schema = [];

    protected array $throws = [];

    protected ?AssertionsCollection $assertions = null;

    protected array $ufoAssertions = [];

    /**
     * @var array<string,array<DtoReflector>>
     */
    protected array $paramsDto = [];

    #[DTO(AttributesCollection::class)]
    protected ?AttributesCollection $attrCollection = null;

    /**
     * @var array<string,ParamDefinition>
     */
    protected array $params = [];

    protected array $defaultParams = [];

    readonly public string $methodName;
    readonly public string $procedure;

    protected bool $deprecated = false;

    public function __construct(
        readonly public string $name,
        readonly public string $procedureFQCN,
        readonly public Info $apiClassInfo,
        readonly public string $concat = Info::DEFAULT_CONCAT,
        readonly public array $uses = [],
    ) {
        $p = explode($this->concat, $this->name, 2);
        $this->methodName = $p[1] ?? $p[0];
        $this->procedure = ($p[1] ?? false) ? $p[0] : 'Main';
        $this->attrCollection = new AttributesCollection();
    }

    /**
     * @return array<string,array<DtoReflector>>
     */
    public function getParamsDto(): array
    {
        return $this->paramsDto;
    }

    public function addParamsDto(string $param, DtoReflector $dtoReflector): static
    {
        $this->paramsDto[$param][] = $dtoReflector;
        return $this;
    }

    /**
     * @return ?DTO
     */
    public function getResponseInfo(): ?DTO
    {
        return $this->responseInfo;
    }

    /**
     * @param ?DTO $responseInfo
     */
    public function setResponseInfo(?DTO $responseInfo): void
    {
        $this->responseInfo = $responseInfo;
    }

    public function getUfoAssertions(): array
    {
        return $this->ufoAssertions;
    }

    public function getUfoAssertion(string $paramName): ?string
    {
        return $this->ufoAssertions[$paramName] ?? null;
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }

    /**
     * @deprecated Since v10.0, will be removed in v11.0.
     *             Use the public property {@see Service::$name} instead.
     * @todo Remove in version 11.0.0
     */
    #[Deprecated]
    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(?string $locale = null): string
    {
        return $this->getTranslation($locale)?->description ?? $this->description;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(?string $locale = null): ?string
    {
        return $this->getTranslation($locale)?->title ?? $this->title;
    }

    public function addTranslation(ServiceTranslation $translation): static
    {
        $this->translations[0] ??= $translation;
        $this->translations[$translation->locale] = $translation;

        return $this;
    }

    protected function getTranslation(?string $locale): ?ServiceTranslation
    {
        if ($locale === null) {
            return null;
        }

        return $this->translations[$locale] ?? $this->translations[0] ?? null;
    }

    public function getThrows(): array
    {
        return $this->throws;
    }

    /**
     * Add a parameter to the service.
     *
     * @param ParamDefinition $paramDefinition
     * @return self
     */
    public function addParam(ParamDefinition $paramDefinition): static
    {
        if ($paramDefinition->isOptional()) {
            $this->defaultParams[$paramDefinition->name] = $paramDefinition->getDefault();
        }
        $this->params[$paramDefinition->name] = $paramDefinition;

        return $this;
    }

    public function setSchema(array $schema): static
    {
        $this->schema = [
            ...$this->schema,
            ...$schema
        ];

        return $this;
    }

    /**
     * Get all parameters.
     *
     * Returns all params in specified order.
     *
     * @return array<string,ParamDefinition>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    public function getDefaultParams(array $params = []): array
    {
        foreach ($this->defaultParams as $key => $value) {
            if (!isset($params[$key])) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * @param array|string $types
     * @param string|null $docType
     * @param string|null $docs
     * @return $this
     * @throws RpcInternalException
     */
    public function setReturn(array|string $types, ?string $docType = null, ?string $docs = null): static
    {
        if ($docType) {
            $this->returnDescription = $docs ?? '';
            $this->return = TypeHintResolver::typeDescriptionToJsonSchema($docType, $this->uses);
        } else {
            if (is_array($types)) {
                $types = implode('|', $types);
            }
            $this->return = TypeHintResolver::typeDescriptionToJsonSchema($types, $this->uses);
        }
        return $this;
    }

    /**
     * @param string $desc
     * @return $this
     */
    public function setDescription(string $desc): static
    {
        $this->description = $desc;

        return $this;
    }

    /**
     * @param array $throws
     * @return static
     */
    public function setThrows(array $throws): static
    {
        $this->throws = $throws;

        return $this;
    }

    /**
     * @param mixed $throw
     * @return static
     */
    public function addThrow(mixed $throw): static
    {
        $this->throws[] = $throw;

        return $this;
    }

    /**
     * Get return type.
     *
     * @return array
     */
    public function getReturn(): array
    {
        return $this->return;
    }

    public function getReturnItems(): ?string
    {
        return $this->returnItems;
    }

    public function setAssertions(AssertionsCollection $assertions): static
    {
        $this->assertions = $assertions;
        return $this;
    }

    public function setUfoAssertions(string $paramName, ?string $ufoAssertion): static
    {
        $this->ufoAssertions[$paramName] = $ufoAssertion;
        return $this;
    }

    /**
     * @throws RpcInternalException
     */
    public function toJson(): string
    {
        return json_encode([
            $this->name => $this->toArray(),
        ]);
    }

    /**
     * Cast to string.
     *
     * @return string
     * @throws RpcInternalException
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * @param array|string $type
     * @return array|string
     */
    public static function validateParamType(array|string $type): array|string
    {
        try {
            return TypeHintResolver::typeDescriptionToJsonSchema($type);
        } catch (TypeError $e) {
            if (is_array($type)) {
                foreach ($type as $key => $value) {
                    $type[$key] = TypeHintResolver::typeDescriptionToJsonSchema($value);
                }
            }
            return $type;
        }
    }

    public function getProcedureFQCN(): string
    {
        return $this->procedureFQCN;
    }

    public function getSchema(): array
    {
        return $this->schema;
    }

    public function getReturnDescription(): string
    {
        return $this->returnDescription;
    }

    public function getAssertions(): ?AssertionsCollection
    {
        return $this->assertions;
    }

    public function getAttrCollection(): AttributesCollection
    {
        return $this->attrCollection;
    }

    public function setAttribute(object $attribute): static
    {
        $this->attrCollection->addAttribute($attribute);
        return $this;
    }

    /**
     * @throws ReflectionException
     */
    public static function fromArray(array $data, array $renameKey = []): static
    {
        /**
         * @var static $self
         */
        $self = ServiceTransformer::fromArray(static::class, $data, $renameKey);
        return $self;
    }

    public function isDeprecated(): bool
    {
        return $this->deprecated;
    }

    public function setDeprecated(): static
    {
        $this->deprecated = true;
        return $this;
    }
}
