<?php

namespace Ufo\JsonRpcBundle\Exceptions;

class ExceptionToArrayTransformer
{
    protected ?ExceptionToArrayTransformer $previous = null;

    public function __construct(protected \Throwable $e, protected string $env)
    {
    }

    protected function getCode(): int
    {
        $code = $this->e->getCode();
        if (!$this->e instanceof AbstractJsonRpcBundleException) {
            $code = AbstractJsonRpcBundleException::DEFAULT_CODE;
        }
        return $code;
    }

    public function getFullInfo(): array
    {
        $info = $this->getShortInfo()
            + [
            'message' => $this->e->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->e->getFile(),
            'line' => $this->e->getLine(),
            'trace' => $this->e->getTrace(),
            'trace_string' => $this->e->getTraceAsString(),
            'previouse' => $this->getPrevious(),
        ];
        return $info;
    }

    /**
     * @return ?array
     */
    public function getPrevious(): ?array
    {
        $data = null;
        try {
            $data = $this->previous->infoByEnvirontment();
        } catch (\Throwable) {
            if (is_null($this->previous) && !is_null($this->e->getPrevious())) {
                $this->previous = new static($this->e->getPrevious(), $this->env);
                $data = $this->getPrevious();
            }
        }
        return $data;
    }

    public function getShortInfo(): array
    {
        return [
            'exception' => $this->e::class,
        ];
    }

    public function infoByEnvirontment(): array
    {
        return ($this->env == "dev") ? $this->getFullInfo() : $this->getShortInfo();
    }
}
