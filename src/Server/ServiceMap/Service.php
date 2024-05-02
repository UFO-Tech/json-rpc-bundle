<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;


use InvalidArgumentException;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC\Response;
use function sha1;

class Service
{
    protected string $envelope = ServiceLocator::ENV_UFO_5;

    protected string $description;

    protected array $return;

    protected ?Response $responseInfo = null;

    protected array $schema = [];

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
//        'schema'=>'is_array'
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

    public function getDescription(): string
    {
        return $this->description;
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

    public function setSchema(array $schema)
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
            'decription' => $this->getDescription(),
            'parameters' => $this->getParams(),
            'json_schema' => $this->schema,
            'returns' => $return,
            'responseFormat' => $this->responseInfo->getResponseFormat()
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
        return TypeHintResolver::normalize($type);
    }

    /**
     * @return IRpcService
     */
    public function getProcedure(): IRpcService
    {
        return $this->procedure;
    }
}
