<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;

interface IServiceHolder
{
    const string TAG = 'rpc.service_holder';

    /**
     * @param string $serviceName
     * @return Service
     * @throws ServiceNotFoundException
     */
    public function getService(string $serviceName): Service;
}