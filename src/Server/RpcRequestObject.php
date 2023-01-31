<?php

namespace Ufo\JsonRpcBundle\Server;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Throwable;
use TypeError;
use Ufo\RpcError\IProcedureExceptionInterface;
use Ufo\RpcError\IServerExceptionInterface;
use Ufo\RpcError\IUserInputExceptionInterface;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcError\RpcBadRequestException;
use Ufo\RpcError\RpcJsonParseException;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\JsonRpcBundle\RpcCallback\CallbackObject;

class RpcRequestObject
{
    const S_GROUP = 'raw';
    const DEFAULT_VERSION = '2.0';

    #[Ignore]
    protected ?Throwable $error = null;

    /**
     * @var RpcRequestRequireParamFromResponse[]
     */
    #[Ignore]
    protected array $require = [];
    #[Ignore]
    protected array $requireIds = [];
    #[Ignore]
    protected ?SpecialRpcParams $rpcParams = null;

    #[Ignore]
    protected ?RpcResponseObject $responseObject = null;

    public function __construct(
        #[Groups([self::S_GROUP])]
        protected string|int $id,
        #[Groups([self::S_GROUP])]
        protected string     $method,
        #[Ignore]
        protected array      $params = [],
        #[Groups([self::S_GROUP])]
        #[SerializedName('jsonrpc')]
        protected string     $version = self::DEFAULT_VERSION,
        #[Ignore]
        protected ?string    $rawJson = null
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
     * @return array
     */
    #[Groups([self::S_GROUP])]
    #[SerializedName('params')]
    public function getAllParams(): array
    {
        return $this->params + $this->getSpecialParams();
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
        $validator = Validation::createValidator();
        $errors = $validator->validate($data, static::getValidationConstrainForBuild());

        $specialParams = [];
        if (isset($data['params'])
            && $matched = preg_grep('/^\\$rpc\./i', array_keys($data['params']))
        ) {
            $params = &$data['params'];

            array_walk($matched, function ($v) use (&$specialParams, &$params) {
                $specialParams[$v] = $params[$v];
                unset($params[$v]);
            });
            unset($params);
        }
        try {
            $object = new static(
                $data['id'] ?? uniqid(),
                (string)$data['method'] ?? '',
                $data['params'] ?? [],
                $data['jsonrpc'] ?? static::DEFAULT_VERSION,
                json_encode($data)
            );
        } catch (Throwable) {
            $object = new static('', '');
        }

        if ($errors->count() > 0) {
            $exceptionMsg = $errors[0]->getPropertyPath() . ': ' . $errors[0]->getMessage();
            $object->setError(new RpcBadRequestException($exceptionMsg));
        }
        if ($specialParams) {
            $object->setRpcParams(SpecialRpcParams::fromArray($specialParams, $object));
        }
        return $object;
    }

    public function toArray(NormalizerInterface $serializer, array $context = []): array
    {
        $context = array_merge([
            AbstractNormalizer::GROUPS => [static::S_GROUP],
        ], $context);
        return $serializer->normalize($this, context: $context);
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
     * @return bool
     * @throws RpcRuntimeException
     */
    public function isAsync(): bool
    {
        return $this->hasRpcParams() && $this->getRpcParams()->hasCallback();
    }

    /**
     * @return string
     * @throws RpcAsyncRequestException
     */
    public function getCallbackUrl(): string
    {
        try {
            return (string)$this->getRpcParams()->getCallbackObject();
        } catch (RpcRuntimeException) {
            throw new RpcAsyncRequestException('Request is not async');
        }
    }

    /**
     * @return CallbackObject
     */
    public function getCallbackObject(): CallbackObject
    {
        if (!$this->isAsync()) {
            throw new RpcAsyncRequestException('Request is not async');
        }
        return $this->getRpcParams()->getCallbackObject();
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
                throw new RpcRuntimeException(
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

        } catch (Throwable $e) {
            $this->error = $e;
        }
    }

    public function refreshRawJson(SerializerInterface $serializer, array $context = [])
    {
        $context = array_merge([
            AbstractNormalizer::GROUPS => [static::S_GROUP]
        ], $context);
        $this->rawJson = $serializer->serialize($this, 'json', $context);
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
        return $this->error instanceof Throwable;
    }

    /**
     * @return Throwable|null
     */
    public function getError(): ?Throwable
    {
        return $this->error;
    }

    /**
     * @param Throwable $error
     */
    public function setError(Throwable $error): void
    {
        $this->error = $error;
    }

    /**
     * @return bool
     */
    public function isProcessed(): bool
    {
        return !is_null($this->responseObject);
    }

    /**
     * @return RpcResponseObject|null
     */
    public function getResponseObject(): ?RpcResponseObject
    {
        return $this->responseObject;
    }

    /**
     * @param RpcResponseObject $responseObject
     */
    public function setResponse(RpcResponseObject $responseObject): void
    {
        $this->responseObject = $responseObject;
    }

    /**
     * @return Assert\Collection Validate ruls for create Request from array
     */
    public static function getValidationConstrainForBuild(): Assert\Collection
    {
        return new Assert\Collection([
            'fields' => [
                'id' => new Assert\Optional([
                    //                new Assert\Type(['int', 'string'], message: 'Request field "{}" must be of type string or int'),
                    new Assert\Type(['int', 'string'],),
                    new Assert\NotBlank(),
                ]),
                'method' => [
                    new Assert\Type('string'),
                    new Assert\Required(),
                    new Assert\NotBlank(),
                ],
                'jsonrpc' => new Assert\Optional([
                    new Assert\Type('string'),
                    new Assert\Regex('/\d\.\d/'),
                ]),
                'params' => new Assert\Optional([

                    new Assert\Type('array'),
                    new Assert\Count(['min' => 1]),
                    new Assert\Collection([
                        'fields' => [
                            '$rpc.callback' => new Assert\Optional(CallbackObject::getValidationConstrainForBuild()),
                            '$rpc.timeout' => new Assert\Optional([
                                new Assert\Optional([
                                    new Assert\NotBlank(),
                                    new Assert\Type(['int', 'float']),
                                    new Assert\Range([
                                        'min'=> 10,
                                        'max' => 120
                                    ])
                                ]),
                            ]),
                        ],
                        'allowExtraFields' => true
                    ]),
                ]),
            ],
            'allowExtraFields' => true
        ]);
    }

    /**
     * @return array
     */
    public function getRequireIds(): array
    {
        return $this->requireIds;
    }

    /**
     * @param array $requireIds
     */
    public function setRequireIds(array $requireIds): void
    {
        $this->requireIds = $requireIds;
    }

    /**
     * @return bool
     */
    public function hasRpcParams(): bool
    {
        return !is_null($this->rpcParams);
    }

    /**
     * @return SpecialRpcParams
     * @throws RpcRuntimeException
     */
    public function getRpcParams(): SpecialRpcParams
    {
        if (!$this->hasRpcParams()) {
            throw new RpcRuntimeException('Additional rpc params not set');
        }
        return $this->rpcParams;
    }

    /**
     * @param SpecialRpcParams $rpcParams
     */
    public function setRpcParams(SpecialRpcParams $rpcParams): void
    {
        $this->rpcParams = $rpcParams;
    }

    public function getSpecialParams(): array
    {
        return $this->getRpcParams()->toArray();
    }
}
