<?php
namespace Ufo\JsonRpcBundle\DocAdapters\Outputs;

use PSX\OpenRPC\Method;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Ufo\DTO\Helpers\TypeHintResolver as T;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\OpenRpc\OpenRpcSpecBuilder;
use Ufo\JsonRpcBundle\DocAdapters\Traits\JsonSchemaDtoFormatTrait;
use Ufo\JsonRpcBundle\Package;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\EnumProcessor\EnumsHolder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RpcTransport;

use function array_flip;
use function array_intersect_key;
use function array_map;
use function array_unique;
use function explode;
use function str_replace;

class OpenRpcAdapter
{
    use JsonSchemaDtoFormatTrait;
    protected OpenRpcSpecBuilder $rpcSpecBuilder;

    protected string $version = Info::DEFAULT_VERSION;

    public function __construct(
        protected ServiceMap $serviceMap,
        protected RpcMainConfig $mainConfig,
        protected ChainParamConvertor $paramConvertor,
        protected RouterInterface $router,
        protected EnumsHolder $enumsHolder,
        protected ?ApiGroupContext $apiGroupContext = null,
        protected ?RequestStack $requestStack = null,
    ) {}

    public function adapt(bool $fullInfo = true, string $version = Info::DEFAULT_VERSION): array
    {
        $this->version = $version;
        $this->schemas = [];
        $this->buildSignature();
        $this->buildServer();
        if ($fullInfo) {
            $this->buildServices();
            $this->buildComponents();
        }
        return $this->rpcSpecBuilder->build();
    }

    protected function buildSignature(): void
    {
        $this->rpcSpecBuilder = OpenRpcSpecBuilder::createBuilder(
            title: $this->mainConfig->docsConfig->projectName,
            description: $this->mainConfig->docsConfig->projectDesc,
            apiVersion: $this->version,
            licenseName: Package::projectLicense(),
            contactName: Package::bundleName(),
            contactLink: Package::bundleDocumentation(),
            versions: $this->serviceMap->getVersions()
        );
    }

    protected function buildComponents(): void
    {
        if (empty($this->schemas)) return;
        $this->rpcSpecBuilder->setComponents($this->schemas);
    }

    protected function buildServer(): void
    {
        $http = RpcTransport::fromArray($this->mainConfig->url);
        $route = $this->version === Info::DEFAULT_VERSION ? ApiController::API_ROUTE : ApiController::API_ROUTE_VER;
        $path = $this->router->generate($this->apiGroupContext?->getRouteName($route) ?? $route, $this->version === Info::DEFAULT_VERSION ? [] : ['ver' => $this->version]);
        $transport = $this->serviceMap->getTransport();
        $transport['sync']['path'] = $path;
        $this->rpcSpecBuilder->addServer(
            $http->getDomainUrl() . $path,
            $this->serviceMap->getEnvelope(),
            $transport,
            rpcEnv: Package::ufoEnvironment()
        );
    }

    protected function buildServices(): void
    {
        foreach ($this->serviceMap->getServices($this->version) as $service) {
            $this->buildService($service);
        }
    }

    protected function buildService(Service $service): void
    {
        $locale = $this->requestStack?->getCurrentRequest()?->getLocale();
        $method = $this->rpcSpecBuilder->buildMethod(
            $service->name,
            $service->getTitle($locale),
            $service->getDescription($locale)
        );
        array_map(
            fn(ParamDefinition $param) => $this->buildParam($method, $param, $service),
            $service->getParams()
        );
        $objSchema = [];
        if (!empty($service->getReturn())) {
            $objSchema = T::applyToSchema(
                $service->getReturn(),
                fn(array $schema) => $this->checkAndGetSchemaFromDesc($schema)
            );
        }

        $this->rpcSpecBuilder->buildResult(
            $method,
            T::jsonSchemaToTypeDescription($service->getReturn()),
            $service->getReturnDescription(),
            $objSchema
        );
        $this->rpcSpecBuilder->buildTag($method, $service->procedure, $service->getProcedureFQCN());

        $throws = [];
        foreach ($service->getThrows() as $rawThrow) {
            $throws = array_unique([
                ...$throws,
                ...explode('|', $rawThrow),
            ]);
        }
        $throwClasses = array_intersect_key($service->uses, array_flip(array_map(fn($throw) => str_replace('\\', '', $throw), $throws)));


        $this->rpcSpecBuilder->buildError($method, $throwClasses);
    }

    /**
     * @throws RpcInternalException
     */
    protected function buildParam(Method $method, ParamDefinition $param, Service $service): void
    {
        $this->rpcSpecBuilder->buildParam(
            $method,
            $param->name,
            $param->description,
            !$param->isOptional(),
            $param->getDefault(),
            $this->schemaForParam($param, $service),
            $service->getUfoAssertion($param->name)
        );
    }

    protected function getParamConvertor(): ChainParamConvertor
    {
        return $this->paramConvertor;
    }

    protected function getEnumsHolder(): EnumsHolder
    {
        return $this->enumsHolder;
    }
}
