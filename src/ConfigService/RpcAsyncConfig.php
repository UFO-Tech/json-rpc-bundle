<?php

namespace Ufo\JsonRpcBundle\ConfigService;

use Ufo\RpcError\RpcAsyncRequestException;

final readonly class RpcAsyncConfig
{
    const string NAME = 'async';
    const string RPC_ASYNC = 'rpc_async';
    const string FAILED = 'failed';
    const string DEFAULT_TYPE = 'amqp';
    const string K_TYPE = 'type';
    const string K_CONFIG = 'config';

    /**
     * @var RpcAsyncInfo[]
     */
    public array $rpcAsync;

    public function __construct(array $rpcConfigs, public RpcMainConfig $parent)
    {
        $types = [];
        $configs = [];
        foreach ($rpcConfigs as $config) {
            $type = $config[self::K_TYPE];
            $types[$type] = ($types[$type] ?? 0) + 1;
            if (isset($configs[$type])) {
                $type = $type . '_' . $types[$type];
            }
            $configs[$type] = RpcAsyncInfo::fromArray($config);
        }
        $this->rpcAsync = $configs;
    }

    public function getConfig(string $type): RpcAsyncInfo
    {
        return $this->rpcAsync[$type] ?? throw new RpcAsyncRequestException('Async type "' . $type . '" is not configured.');
    }

}