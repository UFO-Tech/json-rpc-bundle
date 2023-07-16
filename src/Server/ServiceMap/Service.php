<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;


use InvalidArgumentException;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;

class Service
{
    protected string $envelope = ServiceLocator::ENV_JSONRPC_2;

    protected array $return;

    /** @var string */
    protected string $transport = ServiceLocator::POST;

    /**
     * Parameter option types.
     *
     * @var array
     */
    protected array $paramOptionTypes = [
        'name' => 'is_string',
        'optional' => 'is_bool',
        'default' => null,
        'description' => 'is_string',
    ];

    protected array $params = [];
    protected array $defaultParams = [];

    protected string $methodName;

    /**
     * @param string $name
     */
    public function __construct(protected string $name, protected IRpcService $procedure)
    {
        $t = explode('.', $this->name);
        $this->methodName = end($t);
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

    public function getTransport(): string
    {
        return $this->transport;
    }

    public function getEnvelope(): string
    {
        return $this->envelope;
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

        $this->params[] = $paramOptions;

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
    public function addParams(array $params)
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

    public function getDefaultParams(array $params): array
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
        return [
            'envelope' => $this->getEnvelope(),
            'transport' => $this->getTransport(),
            'name' => $this->getName(),
            'parameters' => $this->getParams(),
            'returns' => $return,
        ];
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
        return TypeHintResolver::normalizeType($type);
    }

    /**
     * @return IRpcService
     */
    public function getProcedure(): IRpcService
    {
        return $this->procedure;
    }
}
