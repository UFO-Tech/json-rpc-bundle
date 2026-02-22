<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\RpcObject\RPC\Info;

interface IServiceHolder
{
    const string TAG = 'rpc.service_holder';

    /**
     * @param string $serviceName
     * @param string $version
     * @return Service
     * @throws ServiceNotFoundException
     */
    public function getService(string $serviceName, string $version = Info::DEFAULT_VERSION): Service;

    /**
     * @return Service[]
     */
    public function getServices(string $version = Info::DEFAULT_VERSION): array;

    /**
     * @return string[]
     */
    public function getVersions(): array;

}