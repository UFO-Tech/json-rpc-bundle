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
            $service = $this->locator->get($id);
            if (!$service instanceof IRpcService) {
                throw new ServiceNotFoundException();
            }
            return $service;
        } catch (NotFoundExceptionInterface) {
            throw new ServiceNotFoundException('Service "'.$id.'" is not found on RPC Service Locator');
        }
    }

    public function has(string $id): bool
    {
        $isset = false;
        try {
            $this->get($id);
            $isset = true;
        } catch (NotFoundExceptionInterface) {}
        return $isset;
    }
}

