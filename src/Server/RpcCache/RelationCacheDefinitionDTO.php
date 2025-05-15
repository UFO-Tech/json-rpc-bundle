<?php

namespace Ufo\JsonRpcBundle\Server\RpcCache;

use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RpcRequest;

use function in_array;

final class RelationCacheDefinitionDTO
{
    /**
     * @param Service[] $services
     */
    protected array $services = [];

    /**
     * @var string[]
     */
    protected array $warmUpsMethods = [];

    /**
     * @var array<string,RpcRequest> $warmupRequests
     */
    protected array $warmupRequests = [];

    /**
     * @return  Service[]
     */
    public function getServices(): array
    {
        return $this->services;
    }

    public function getWarmUpsMethods(): array
    {
        return $this->warmUpsMethods;
    }

    public function checkWarmUpsMethod(Service $service, RpcRequest $request): void
    {
        if (in_array($service->getName(), $this->warmUpsMethods)) {
            $this->setWarmupRequest($service->getName(), $request);
        }
    }

    public function addService(Service $service): self
    {
        $this->services[] = $service;
        return $this;
    }

    public function addServices(array $service): self
    {
        $this->services = [
            ...$this->services,
            ...$service
        ];
        return $this;
    }

    public function addWarmUpsMethod(string $warmUpsMethod): self
    {
        $this->warmUpsMethods[] = $warmUpsMethod;
        return $this;
    }

    /**
     * @return  array<string,RpcRequest>
     */
    public function getWarmupRequests(): array
    {
        return $this->warmupRequests;
    }

    public function setWarmupRequest(string $method, RpcRequest $request): self
    {
        $this->warmupRequests[$method] = $request;
        return $this;
    }

}