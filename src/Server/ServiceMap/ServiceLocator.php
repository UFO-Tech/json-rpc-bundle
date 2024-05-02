<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;


use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;

class ServiceLocator implements ContainerInterface
{
    const ENV_JSONRPC_2 = 'JSON-RPC-2.0';
    const ENV_UFO_5 = 'JSON-RPC-2.0/UFO-RPC-5';
    const JSON = 'application/json';
    const POST = 'POST';
    /**
     * Content type.
     *
     * @var string
     */
    protected string $contentType = self::JSON;
    /**
     * Service description.
     *
     * @var string
     */
    protected string $description = '';
    /**
     * Current envelope.
     *
     * @var string
     */
    protected string $envelope = self::ENV_UFO_5;
    /**
     * Service id.
     *
     * @var string
     */
    protected string $id = '';
    /**
     * Services offered.
     *
     * @var array
     */
    protected array $services = [];
    /**
     * Service target.
     *
     * @var string
     */
    protected string $target;
    /**
     * Global transport.
     *
     * @var string
     */
    protected string $transport = self::POST;

    /**
     * Get transport.
     *
     * @return string
     */
    public function getTransport(): string
    {
        return $this->transport;
    }

    /**
     * Retrieve envelope.
     *
     * @return string
     */
    public function getEnvelope(): string
    {
        return $this->envelope;
    }

    /**
     * Retrieve content type
     *
     * Content-Type of response; default to application/json.
     *
     * @return string
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function setTarget(string $target): static
    {
        $this->target = $target;
        return $this;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function setId(string $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param Service $service
     * @return $this
     */
    public function addService(Service $service): static
    {
        $name = $service->getName();
        if (array_key_exists($name, $this->services)) {
            throw new RuntimeException('Attempt to register a service already registered detected');
        }
        $this->services[$name] = $service;
        return $this;
    }

    /**
     * @param Service[] $services
     * @return $this
     */
    public function addServices(array $services): static
    {
        foreach ($services as $service) {
            $this->addService($service);
        }
        return $this;
    }

    /**
     * @param string $id
     * @return Service
     * @throws ServiceNotFoundException
     */
    public function get(string $id): Service
    {
        if (!$this->has($id)) {
            throw new ServiceNotFoundException('Service "' . $id . '" is not found on RPC Service Locator');
        }
        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        if (!array_key_exists($id, $this->services)) {
            return false;
        }
        return true;
    }

    /**
     * @return Service[]
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * @throws RpcMethodNotFoundExceptionRpc
     */
    public function getService(string $name): Service
    {
        if (isset($this->services[$name])) {
            return $this->services[$name];
        }
        throw new RpcMethodNotFoundExceptionRpc("Method '$name' is not found");
    }

    public function removeService(string $name): bool
    {
        if (!array_key_exists($name, $this->services)) {
            return false;
        }
        unset($this->services[$name]);
        return true;
    }

    /**
     * Cast to array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $description = $this->getDescription();
        $transport = $this->getTransport();
        $envelope = $this->getEnvelope();
        $contentType = $this->getContentType();
        $service = [
            'transport'   => $transport,
            'envelope'    => $envelope,
            'contentType' => $contentType,
            'description' => $description,
        ];
        if (null !== ($target = $this->getTarget())) {
            $service['target'] = $target;
        }
        if (null !== ($id = $this->getId())) {
            $service['id'] = $id;
        }
        $services = $this->getServices();
        if (empty($services)) {
            return $service;
        }
        $service['services'] = [];
        foreach ($services as $name => $svc) {
            $service['services'][$name] = $svc->toArray();
        }
        $service['methods'] = $service['services'];
        return $service;
    }
}
