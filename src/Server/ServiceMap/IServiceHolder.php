<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\RpcObject\RPC\Info;


interface IServiceHolder
{
    const string SERVICE = 'rpc.service';
    const string TAG = self::SERVICE . '_holder';
    const string LOCATOR = self::SERVICE . '_locator';
    const string ARG_LOCATOR = self::SERVICE . '_argument_locator';
    const string MAP = self::SERVICE . '_map';

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
