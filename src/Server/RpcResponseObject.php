<?php

namespace Ufo\JsonRpcBundle\Server;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Serializer\Annotation\SerializedName;

class RpcResponseObject
{
    const IS_RESULT = 'result';
    const IS_ERROR = 'error';
    
    public function __construct(
        #[Groups([self::IS_RESULT, self::IS_ERROR])]
        protected string|int $id,

        #[Groups([self::IS_RESULT])]
        protected array $result = [],

        #[Groups([self::IS_ERROR])]
        protected ?RpcErrorObject $error = null,

        #[Groups([self::IS_RESULT, self::IS_ERROR])]
        #[SerializedName('jsonrpc')]
        protected string $version = RpcRequestObject::DEFAULT_VERSION,

        #[Ignore] protected ?RpcRequestObject $requestObject = null
    )
    {
    }

    public static function fromReques()
    {
        
    }

    /**
     * @return int|string
     */
    public function getId(): int|string
    {
        return $this->id;
    }

    /**
     * @return array
     */
    public function getResult(): array
    {
        return $this->result;
    }

    /**
     * @return mixed|null
     */
    public function getError(): mixed
    {
        return $this->error;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return RpcRequestObject|null
     */
    public function getRequestObject(): ?RpcRequestObject
    {
        return $this->requestObject;
    }

    public function getResponseSignature(): string
    {
        return is_null($this->error) ? static::IS_RESULT : static::IS_ERROR;
    }
}
