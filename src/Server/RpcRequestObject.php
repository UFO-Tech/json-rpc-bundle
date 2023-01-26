<?php

namespace Ufo\JsonRpcBundle\Server;

use Laminas\Json\Server\Request as ServerRequestObject;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use TypeError;
use Ufo\JsonRpcBundle\Exceptions\AbstractJsonRpcBundleException;
use Ufo\JsonRpcBundle\Exceptions\IProcedureExceptionInterface;
use Ufo\JsonRpcBundle\Exceptions\IServerExceptionInterface;
use Ufo\JsonRpcBundle\Exceptions\IUserInputExceptionInterface;
use Ufo\JsonRpcBundle\Exceptions\RpcBadRequestException;
use Ufo\JsonRpcBundle\Exceptions\RpcJsonParseException;
use Ufo\JsonRpcBundle\Exceptions\RuntimeException;

class RpcRequestObject
{
    const DEFAULT_VERSION = '2.0';

    protected ?\Throwable $error = null;

    /**
     * @var RpcRequestRequireParamFromResponse[]
     */
    protected array $require = [];
    protected array $requireIds = [];

    public function __construct(
        protected string|int $id,
        protected string     $method,
        protected array      $params = [],
        protected string     $version = self::DEFAULT_VERSION,
        protected ?string    $rawJson = null,
        protected array      $async = []
    )
    {
        $this->analyzeParams();
    }

    protected function analyzeParams()
    {
        $this->clearRequire();
        if ($this->hasParams()
            && $matched = preg_grep('/^\@FROM\:/i', $this->getParams())
        ) {

            $self = $this;
            array_walk($matched, function ($value, $parmName) use ($self) {
                $data = [];
                preg_match('/^\@FROM\:(\w+)\((\w+)\)$/i', $value, $data);
                $requireRequestId = &$data[1];
                $requireFieldName = &$data[2];
                $self->require[$parmName] = new RpcRequestRequireParamFromResponse($requireRequestId, $requireFieldName);
                $self->requireIds[$requireRequestId] = $self->requireIds[$requireRequestId] ?? 0;
                $self->requireIds[$requireRequestId]++;
            });
        }
    }

    protected function clearRequire()
    {
        $this->require = [];
        $this->requireIds = [];
    }

    public function hasParams(): bool
    {
        return !empty($this->params);
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @param $json
     * @return static
     * @throws RpcBadRequestException
     * @throws RpcJsonParseException
     */
    public static function fromJson($json): static
    {
        try {
            return static::fromArray(json_decode($json, true), $json);
        } catch (TypeError $e) {
            throw new RpcJsonParseException('Invalid json data', previous: $e);
        }
    }

    /**
     * @param array $data
     * @return static
     * @throws RpcBadRequestException
     */
    public static function fromArray(array $data): static
    {
        $object = new static(
            $data['id'] ?? uniqid(),
            $data['method'] ?? '',
            $data['params'] ?? [],
            $data['version'] ?? static::DEFAULT_VERSION,
            json_encode($data),
            $data['async'] ?? []
        );
        
        if (!isset($data['method'])) {
            $object->setError(
                throw new RpcBadRequestException('Message must have attribute "method"')
            );
        }
        return $object;
    }

    public function toArray(NormalizerInterface $serializer): array
    {
        return $serializer->normalize($this);
    }

    /**
     * @return int|string
     */
    public function getId(): int|string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return string|null
     */
    public function getRawJson(): ?string
    {
        return $this->rawJson;
    }

    /**
     * @return array
     */
    public function getAsync(): array
    {
        return $this->async;
    }

    public function checkRequireId(string|int $id): bool
    {
        return isset($this->requireIds[$id]);
    }

    public function getCurrentRequireId(): string|int|null
    {
        return array_key_first($this->requireIds);
    }

    public function replaceRequestParam(string $paramName, mixed $newValue): void
    {
        try {
            if (!$this->hasRequire()) {
                throw new RuntimeException(
                    sprintf(
                        'The request does not need to replace parameter "%s".',
                        $paramName
                    )
                );
            }

            if (!isset($this->getRequire()[$paramName])) {
                throw new RpcBadRequestException(
                    sprintf(
                        'The parameter "%s" is not found on request.',
                        $paramName
                    )
                );
            }
            
            $this->params[$paramName] = $newValue;
            $this->analyzeParams();
            
        } catch (\Throwable $e) {
            $this->error = $e;
        }
    }

    public function hasRequire(): bool
    {
        return !empty($this->require);
    }

    /**
     * @return RpcRequestRequireParamFromResponse[]
     */
    public function getRequire(): array
    {
        return $this->require;
    }

    /**
     * @return bool
     */
    public function isUserError(): bool
    {
        return $this->error instanceof IUserInputExceptionInterface;
    }

    /**
     * @return bool
     */
    public function isProcedureError(): bool
    {
        return $this->error instanceof IProcedureExceptionInterface;
    }

    /**
     * @return bool
     */
    public function isServerError(): bool
    {
        return $this->error instanceof IServerExceptionInterface;
    }

    public function hasError(): bool
    {
        return $this->error instanceof \Throwable;
    }

    /**
     * @return \Throwable|null
     */
    public function getError(): ?\Throwable
    {
        return $this->error;
    }

    /**
     * @param \Throwable $error
     */
    public function setError(\Throwable $error): void
    {
        $this->error = $error;
    }
}
