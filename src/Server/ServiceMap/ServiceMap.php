<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use Psr\Container\ContainerInterface;
use RuntimeException;
use Ufo\JsonRpcBundle\ConfigService\RpcDocsConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\JsonRpcBundle\Package;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;
use Ufo\RpcObject\RpcTransport;

use function array_key_exists;
use function is_null;
use function is_subclass_of;

class ServiceMap
{
    const string ENV_JSON_RPC_2 = 'JSON-RPC-2.0';
    const string POST = 'POST';

    protected string $envelope;
    protected array $services = [];

    public function __construct(
        protected string $target,
        protected RpcMainConfig $mainConfig
    ) {}

    /**
     * Get transport.
     *
     * @return array[]
     */
    public function getTransport(): array
    {
        $http = RpcTransport::fromArray($this->mainConfig->url);
        $transport = [
            'sync' => $http->toArray(),
        ];
        $transport['sync'] += [
            'method' => self::POST,
        ];
        if ($this->mainConfig->docsConfig->asyncDsnInfo && !is_null($this->mainConfig->asyncConfig->rpcAsync)) {
            $async = RpcTransport::fromDsn($this->mainConfig->asyncConfig->rpcAsync);
            $transport['async'] = $async->toArray();
        }

        return $transport;
    }

    public function getEnvelope(): string
    {
        if (!isset($this->envelope)) {
            $this->envelope = self::ENV_JSON_RPC_2.'/UFO-RPC-' . Package::version();
        }
        return $this->envelope;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function getDescription(): string
    {
        return $this->mainConfig->docsConfig->projectDesc;
    }

    /**
     * @param Service $service
     * @return $this
     */
    public function addService(Service $service): static
    {
        $name = $service->getName();
        if (array_key_exists($name, $this->services)
            && !is_subclass_of($service->getProcedureFQCN(), $this->services[$name]->getProcedureFQCN())
        ) {
            if (is_subclass_of($this->services[$name]->getProcedureFQCN(), $service->getProcedureFQCN())) {
                return $this;
            } else {
                throw new RuntimeException('Attempt to register a service "'. $name .'" already registered detected');
            }
        }
        $this->services[$name] = $service;
        return $this;
    }


    /**
     * @return Service[]
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * @return Service
     * @throws ServiceNotFoundException
     */
    public function getService(string $serviceName): Service
    {
        return $this->services[$serviceName] ?? throw new ServiceNotFoundException('Service "'.$serviceName.'" is not found on RPC Service Map');
    }
}

