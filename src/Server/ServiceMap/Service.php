<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use ReflectionException;
use TypeError;
use Ufo\DTO\DTOTransformer;
use Ufo\DTO\ServiceTransformer;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\DtoReflector;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\RpcError\RpcInternalException;
use Ufo\DTO\Interfaces\IArrayConstructible;
use Ufo\DTO\Interfaces\IArrayConvertible;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\RpcObject\RPC\AssertionsCollection;
use Ufo\RpcObject\RPC\DTO;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RPC\ResultAsDTO;
use Ufo\DTO\JsonSerializableTrait;

use function array_key_exists;
use function count;
use function end;
use function explode;
use function is_array;
use function json_encode;

class Service implements IArrayConvertible, IArrayConstructible
{
    use JsonSerializableTrait;

    protected string $description = '';

    protected array $return = [];

    protected string $returnDescription = '';
    protected ?string $returnItems = null;

    #[DTO(ResultAsDTO::class, context: [
        ResultAsDTO::C_RENAME_KEYS => ['dtoFormat' => 'format'],
    ])]
    protected ?ResultAsDTO $responseInfo = null;

    protected array $schema = [];

    protected array $throws = [];

    protected ?AssertionsCollection $assertions = null;

    protected array $ufoAssertions = [];

    /**
     * @var array<string,array<DtoReflector>
     */
    protected array $paramsDto = [];

    #[DTO(AttributesCollection::class)]
    protected ?AttributesCollection $attrCollection = null;

    /**
     * @var array<string,ParamDefinition>
     */
    protected array $params = [];

    protected array $defaultParams = [];

    protected string $methodName;

    public function __construct(
        protected string $name,
        protected string $procedureFQCN,
        public readonly string $concat = Info::DEFAULT_CONCAT,
        readonly public array $uses = []
    ) {
        $t = explode($this->concat, $this->name);
        $this->methodName = end($t);
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
     * @return ?ResultAsDTO
     */
    public function getResponseInfo(): ?ResultAsDTO
    {
        return $this->responseInfo;
    }

    /**
     * @param ?ResultAsDTO $responseInfo
     */
    public function setResponseInfo(?ResultAsDTO $responseInfo): void
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
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
     * Add params.
     *
     * Each param should be an array, and should include the key 'type'.
     *
     * @param array $params
     * @return self
     * @throws RpcInternalException
     */
    public function addParams(array $params): static
    {
        foreach ($params as $options) {
            if (!is_array($options)) {
                continue;
            }
            if (!array_key_exists('type', $options)) {
                continue;
            }
            $type = $options['type'];
            $this->addParam($type, $options);
        }

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
     * @throws RpcInternalException
     */
    public function addReturn(string $type, ?string $desc = null, ?string $items = null): static
    {
        $this->return[] = static::validateParamType($type);
        $this->returnDescription = $desc ?? '';
        $this->returnItems = $items;
        return $this;
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
                foreach ($types as $returnType) {
                    $this->addReturn($returnType);
                }
            } else {
                $this->return = TypeHintResolver::typeDescriptionToJsonSchema($types, $this->uses);
            }
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

    /**
     * @throws RpcInternalException
     */
    public function toArray(): array
    {
        $array = [
            'name'           => $this->getName(),
            'description'    => $this->getDescription(),
            'parameters'     => $this->getParams(),
            'returns'        => (count($this->getReturn()) > 1) ? $this->getReturn() : $this->getReturn()[0],
            'returnItems'    => $this->returnItems,
            'responseFormat' => $this->responseInfo->getResponseFormat() ?? $this->return,
            'attrCollection' => DTOTransformer::toArray($this->attrCollection),
            'uses'           => $this->uses,
        ];
        if (!empty($this->throws)) {
            $array['throws'] = $this->throws;
        }
        if (!empty($this->schema)) {
            $array['json_schema'] = $this->schema;
        }
        if (!empty($this->assertions)) {
            $array['symfony_assertions'] = $this->assertions->toArray();
        }

        return $array;
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
            $this->getName() => $this->toArray(),
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
}
