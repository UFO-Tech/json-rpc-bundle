<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use ReflectionException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\JsonRpcBundle\Package;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RpcTransport;

use function array_key_exists;
use function array_keys;
use function array_unique;
use function in_array;
use function is_subclass_of;
use function usort;

#[AutoconfigureTag(IServiceHolder::TAG)]
class ServiceMap implements IServiceHolder
{
    const string ENV_JSON_RPC_2 = 'JSON-RPC-2.0';
    const string POST = 'POST';

    protected string $envelope;
    protected array $services = [];
    protected array $excludePrevious = [];
    protected bool $fromCache = false;

    public function __construct(
        #[Autowire(param: IServiceHolder::MAP)]
        protected array $serviceMapData,
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
        if ($this->mainConfig->docsConfig->asyncDsnInfo && !empty($this->mainConfig->asyncConfig->rpcAsync)) {
            foreach ($this->mainConfig->asyncConfig->rpcAsync as $asyncInfo) {
                $config = $asyncInfo->config;
                if ($dsn = $asyncInfo->config['dsn'] ?? false) {
                    unset($config['dsn']);
                    $dsnConfig = RpcTransport::fromDsn($dsn)->toArray();
                    $config = [
                        ...$dsnConfig,
                        ...$config,
                    ];
                }
                $transport[$asyncInfo->name] = $config;
            }
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

    /**
     * @param string $version
     * @return Service[]
     * @throws ReflectionException
     */
    public function getServices(string $version = Info::DEFAULT_VERSION): array
    {
        $services = $this->serviceMapData[$version]
                    ?? throw new RuntimeException('Version "'.$version.'" is not registered on RPC Service Map');

        foreach ($services as $serviceName => $data) {
            $this->services[$version][$serviceName] ??= Service::fromArray($data);
        }
        return $this->services[$version];
    }

    /**
     * @param string $serviceName
     * @param string $version
     * @return Service
     * @throws ServiceNotFoundException|\ReflectionException
     */
    public function getService(string $serviceName, string $version = Info::DEFAULT_VERSION): Service
    {
        return $this->services[$version][$serviceName] ??= Service::fromArray($this->serviceMapData[$version][$serviceName])
                                                           ?? $this->errorMessage($serviceName, $version);
    }

    protected function errorMessage(string $serviceName, string $version): never
    {
        throw new ServiceNotFoundException('Service "'.$serviceName.'" for version "'.$version.'" is not found on RPC Service Map');
    }

    public function getVersions(): array
    {
        return array_keys($this->serviceMapData);
    }

}

