<?php

namespace Ufo\JsonRpcBundle\Server;

use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\JsonRpcBundle\RpcCallback\CallbackObject;

class SpecialRpcParams
{
    const PREFIX = '$rpc.';
    /**
     * Timeout for request. Second
     */
    const DEFAULT_TIMEOUT = 10;

    public function __construct(
        protected RpcRequestObject $parent,
        protected null|string|CallbackObject $callbackObject = null,
        protected float $timeout = self::DEFAULT_TIMEOUT
    )
    {
        if (is_string($this->callbackObject)) {
            try {
                $this->callbackObject = new CallbackObject($this->callbackObject);
            } catch (AbstractRpcErrorException $e) {
                $this->parent->setError($e);
            }
        }
    }

    public static function fromArray(array $data, RpcRequestObject $parent): static
    {
        return new static(
            $parent,
            $data['$rpc.callback'] ?? null,
            $data['$rpc.timeout'] ?? static::DEFAULT_TIMEOUT
        );
    }

    /**
     * @return CallbackObject
     * @throws RpcRuntimeException
     */
    public function getCallbackObject(): CallbackObject
    {
        if (!$this->hasCallback()) {
            throw new RpcRuntimeException('Callback is not set');
        }
        return $this->callbackObject;
    }

    /**
     * @return float|int
     */
    public function getTimeout(): float|int
    {
        return $this->timeout;
    }

    public function hasCallback(): bool
    {
        return !is_null($this->callbackObject);
    }

    public function toArray()
    {
        $o = [
            self::PREFIX . 'timeout' => $this->timeout
        ];
        if ($this->hasCallback()) {
            $o[self::PREFIX . 'callback'] = $this->getCallbackObject()->getTarget();
        }
        return $o;
    }
}
