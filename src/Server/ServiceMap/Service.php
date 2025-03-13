<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionObject;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcObject\Helpers\TypeHintResolver;
use Ufo\RpcObject\RPC\AssertionsCollection;
use Ufo\RpcObject\RPC\Cache;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RPC\Lock;
use Ufo\RpcObject\RPC\Response;
use Ufo\RpcObject\RPC\ResultAsDTO;

use function is_null;

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
     * @throws ReflectionException
     * @throws RpcInternalException
     */
    public static function fromArray(array $data): static
    {
        $refClass = new ReflectionClass(static::class);
        $service = $refClass->newInstanceWithoutConstructor();
        $data['assertions'] = null;
        foreach ($data as $propertyName => $value) {
            if ($propertyName === 'responseInfo') {
                if (is_null($value)) continue;
                $rf = $value['responseFormat'];
                $value = new ResultAsDTO(
                    $value['dtoFQCN'],
                    $value['collection']
                );
                $refResultAsDTO = new ReflectionObject($value);
                $refResultAsDTO->getProperty('dtoFormat')->setValue($value, $rf);
            }
            $refClass->getProperty($propertyName)->setValue($service, $value);
        }
        return $service;
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
        if (is_string($type)) {
            $type = $this->validateParamType($type);
        }
        if (is_array($type)) {
            foreach ($type as $key => $paramType) {
                $type[$key] = $this->validateParamType($paramType);
            }
        }
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
        $this->schema = $schema;

        return $this;
    }

    /**
     * Add params.
     *
     * Each param should be an array, and should include the key 'type'.
     *
     * @param array $params
     * @return self
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
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * @param string $type
     * @return string
     */
    protected function validateParamType(string $type): string
    {
        return TypeHintResolver::normalize($type);
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
