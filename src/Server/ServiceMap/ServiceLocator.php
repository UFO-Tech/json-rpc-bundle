<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;

use function is_null;

class ServiceLocator implements ContainerInterface
{
    public function __construct(
        protected ContainerInterface $locator
    ) {}

    /**
     * @param string $id
     * @return IRpcService
     * @throws ContainerExceptionInterface
     * @throws ServiceNotFoundException
     */
    public function get(string $id): IRpcService
    {
        try {
            return $this->locator->get($id);
        } catch (NotFoundExceptionInterface) {
            throw new ServiceNotFoundException('Service "'.$id.'" is not found on RPC Service Locator');
        }
    }

    public function has(string $id): bool
    {
        return $this->locator->has($id);
    }
}

