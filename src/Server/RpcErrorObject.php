<?php

namespace Ufo\JsonRpcBundle\Server;

use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Normalizer\ProblemNormalizer;

class RpcErrorObject
{
    const IS_RESULT = 'result';
    const IS_ERROR = 'error';

    public function __construct(
        #[Groups([self::IS_ERROR])]
        protected int $code,

        #[Groups([self::IS_ERROR])]
        protected string $message,

        #[Groups([self::IS_ERROR])]
        protected \Throwable $data
    )
    {

    }

    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return \Throwable
     */
    public function getData(): \Throwable
    {
        return $this->data;
    }


}
