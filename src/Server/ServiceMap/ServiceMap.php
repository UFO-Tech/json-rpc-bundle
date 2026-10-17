<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use ReflectionException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\JsonRpcBundle\Package;
use Ufo\JsonRpcBundle\Server\RpcCache\RpcMetadataCache;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RPC\APIGroups;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
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

    public function __construct(
        #[Autowire(param: IServiceHolder::MAP)]
        protected array $serviceMapData,
        protected RpcMainConfig $mainConfig,
        protected ?ApiGroupContext $apiGroupContext = null,
        #[Autowire(param: IServiceHolder::GROUP_MAP)]
        protected array $groupMapData = [],
        #[Autowire(param: IServiceHolder::LOCALES)]
        protected array $locales = [],
        protected ?RpcMetadataCache $metadataCache = null,
    ) {}

    /**
     * Locales that have RPC translations, the default locale first.
     *
     * @return string[]
     */
    public function getLocales(): array
    {
        return $this->locales;
    }


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
        $services = $this->currentMap()[$version]
                    ?? throw new RuntimeException('Version "'.$version.'" is not registered on RPC Service Map');

        $key = $this->cacheVersion($version);
        $this->services[$key] ??= [];
        foreach ($services as $serviceName => $data) {
            $this->services[$key][$serviceName] ??= $this->hydrate($key, $serviceName, $data);
        }
        return $this->services[$key];
    }

    /**
     * @param string $serviceName
     * @param string $version
     * @return Service
     * @throws ServiceNotFoundException|\ReflectionException
     */
    public function getService(string $serviceName, string $version = Info::DEFAULT_VERSION): Service
    {
        $key = $this->cacheVersion($version);
        return $this->services[$key][$serviceName] ??= $this->hydrate(
            $key,
            $serviceName,
            $this->currentMap()[$version][$serviceName] ?? $this->errorMessage($serviceName, $version)
        );
    }

    /**
     * Service::fromArray() is reflection-based and costs ~1-3 ms per method, paid on every FPM request.
     * The hydrated object is kept in the metadata cache; $key carries the API group and version,
     * because the same method name may have a different implementation in another group.
     */
    protected function hydrate(string $key, string $serviceName, array $data): Service
    {
        $build = fn() => Service::fromArray($data);
        return $this->metadataCache?->get('service.'.$key.'.'.$serviceName, Service::class, $build) ?? $build();
    }

    protected function errorMessage(string $serviceName, string $version): never
    {
        throw new ServiceNotFoundException('Service "'.$serviceName.'" for version "'.$version.'" is not found on RPC Service Map');
    }

    public function getVersions(): array
    {
        return array_keys($this->currentMap());
    }

    private function currentMap(): array
    {
        $group = $this->apiGroupContext?->getGroup() ?? APIGroups::DEFAULT_GROUP;
        return $this->groupMapData[$group] ?? ($group === APIGroups::DEFAULT_GROUP ? $this->serviceMapData : []);
    }

    private function cacheVersion(string $version): string
    {
        $group = $this->apiGroupContext?->getGroup() ?? APIGroups::DEFAULT_GROUP;
        return $group === APIGroups::DEFAULT_GROUP ? $version : $group.':'.$version;
    }

}

