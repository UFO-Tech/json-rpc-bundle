<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionObject;
use TypeError;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\DtoReflector;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcObject\DTO\DTOTransformer;
use Ufo\RpcObject\Helpers\TypeHintResolver;
use Ufo\RpcObject\RPC\AssertionsCollection;
use Ufo\RpcObject\RPC\Cache;
use Ufo\RpcObject\RPC\DTO;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RPC\Lock;
use Ufo\RpcObject\RPC\Response;
use Ufo\RpcObject\RPC\ResultAsDTO;

use function array_key_exists;
use function array_keys;
use function class_exists;
use function count;
use function end;
use function explode;
use function in_array;
use function is_array;
use function is_null;
use function json_encode;

class Service
{
    protected string $description;

    protected array $return;

    protected string $returnDescription = '';

    protected ?ResultAsDTO $responseInfo = null;

    protected array $schema = [];

    protected array $throws = [];

    protected ?AssertionsCollection $assertions = null;

    protected ?Cache $cacheInfo = null;

    protected ?Lock $lockInfo = null;

    /**
     * @var array<string,DtoReflector>
     */
    protected array $paramsDto = [];

    public function getParamDto(string $param): ?DtoReflector
    {
        return $this->paramsDto[$param] ?? null;
    }

    public function getParamsDto(): array
    {
        return $this->paramsDto;
    }

    public function addParamsDto(string $param, DtoReflector $dtoReflector): static
    {
        $this->paramsDto[$param] = $dtoReflector;
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

    /**
     * Parameter option types.
     *
     * @var array
     */
    protected array $paramOptionTypes = [
        'name'        => 'is_string',
        'optional'    => 'is_bool',
        'default'     => null,
        'description' => 'is_string',
    ];

    protected array $params = [];

    protected array $defaultParams = [];

    protected string $methodName;

    public function __construct(
        protected string $name,
        protected string $procedureFQCN,
        public readonly string $concat = Info::DEFAULT_CONCAT
    ) {
        $t = explode($this->concat, $this->name);
        $this->methodName = end($t);
    }

    /**
     * @param array $data
     * @return Service
     * @throws ReflectionException
     */
    public static function fromArray(array $data): static
    {
        $refClass = new ReflectionClass(static::class);
        $service = $refClass->newInstanceWithoutConstructor();
        $data['assertions'] = null;
        foreach ($data as $propertyName => $value) {
            try {
                $value = match ($propertyName) {
                    'responseInfo', 'paramsDto' => static::hydrateResponseInfo($value),
                    default => $value,
                };
            } catch (\Throwable) {
                continue;
            }

            $refClass->getProperty($propertyName)->setValue($service, $value);
        }
        return $service;
    }

    protected static function hydrateResponseInfo(array $value): ResultAsDTO
    {
        $objects = $rf = $value['format'];
        $result = new ResultAsDTO($value['dtoFQCN'], $value['collection']);
        foreach ($rf['$collections'] ?? [] as $param => $data) {
            $objects['$collections'][$param] = static::hydrateResponseInfo($data);
        }
        (new ReflectionObject($result))->getProperty('dtoFormat')->setValue($result, $objects);
        return $result;
    }

    public function setCacheInfo(Cache $cacheInfo): static
    {
        $this->cacheInfo = $cacheInfo;
        return $this;
    }

    public function getCacheInfo(): ?Cache
    {
        return $this->cacheInfo;
    }

    public function setLockInfo(?Lock $lockInfo): static
    {
        $this->lockInfo = $lockInfo;

        return $this;
    }

    public function getLockInfo(): ?Lock
    {
        return $this->lockInfo;
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
     * @param string|array $type
     * @param array $options
     * @return self
     * @throws InvalidArgumentException
     */
    public function addParam(string|array $type, array $options = []): static
    {
        $type = $this->validateParamType($type);
        $paramOptions = ['type' => $type];
        foreach ($options as $key => $value) {
            if (in_array($key, array_keys($this->paramOptionTypes))) {
                $paramOptions[$key] = $value;
                if ($key === 'default') {
                    $this->defaultParams[$options['name']] = $value;
                }
            }
        }
        $this->params[$options['name']] = $paramOptions;

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
     * @return array
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
     * @param string $type
     * @param string|null $desc
     * @return $this
     * @throws RpcInternalException
     */
    public function addReturn(string $type, ?string $desc = null): static
    {
        $this->return[] = $this->validateParamType($type);
        $this->returnDescription = $desc ?? '';
        return $this;
    }

    /**
     * @param array $types
     * @return $this
     * @throws RpcInternalException
     */
    public function setReturn(array $types): static
    {
        foreach ($types as $key => $returnType) {
            $this->return[$key] = $this->addReturn($returnType);
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

    /**
     * @throws RpcInternalException
     */
    public function toArray(): array
    {
        $return = $this->getReturn()[0];
        if (count($this->getReturn()) > 1) {
            $return = $this->getReturn();
        }
        $array = [
            'name'           => $this->getName(),
            'description'    => $this->getDescription(),
            'parameters'     => $this->getParams(),
            'returns'        => $return,
            'responseFormat' => $this->responseInfo->getResponseFormat() ?? $return,
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
    protected function validateParamType(array|string $type): array|string
    {
        try {
            return TypeHintResolver::normalize($type);
        } catch (TypeError) {
            return TypeHintResolver::normalizeArray($type);
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

}
