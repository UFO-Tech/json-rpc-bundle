<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use InvalidArgumentException;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\Assertions;
use Ufo\RpcObject\RPC\AssertionsCollection;
use Ufo\RpcObject\RPC\Cache;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RPC\Response;

class Service
{
    protected string $description;

    protected array $return;

    protected ?Response $responseInfo = null;

    protected array $schema = [];

    protected array $throws = [];

    protected ?AssertionsCollection $assertions = null;

    protected ?Cache $cacheInfo = null;

    /**
     * @return Response|null
     */
    public function getResponseInfo(): ?Response
    {
        return $this->responseInfo;
    }

    /**
     * @param Response|null $responseInfo
     */
    public function setResponseInfo(?Response $responseInfo): void
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
        protected IRpcService $procedure,
        public readonly string $concat = Info::DEFAULT_CONCAT
    ) {
        $t = explode($this->concat, $this->name);
        $this->methodName = end($t);
    }

    /**
     * @param Cache $cacheInfo
     * @return static
     */
    public function setCacheInfo(Cache $cacheInfo): static
    {
        $this->cacheInfo = $cacheInfo;

        return $this;
    }

    public function getCacheInfo(): ?Cache
    {
        return $this->cacheInfo;
    }

    /**
     * @return string
     */
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
     * @return $this
     */
    public function addReturn(string $type): static
    {
        $this->return[] = $this->validateParamType($type);

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

    /**
     * @return IRpcService
     */
    public function getProcedure(): IRpcService
    {
        return $this->procedure;
    }

    public function getSchema(): array
    {
        return $this->schema;
    }

}
