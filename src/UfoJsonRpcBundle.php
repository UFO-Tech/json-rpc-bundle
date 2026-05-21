<?php

namespace Ufo\JsonRpcBundle;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\RegisterControllerArgumentLocatorsPass;
use Ufo\JsonRpcBundle\DependencyInjection\CompilerPass\RpcRegisterControllerArgumentLocatorsPass;
use Ufo\JsonRpcBundle\DependencyInjection\CompilerPass\RpcServiceMapPass;
use Ufo\RpcObject\Transformer\DTOTransformerBundleBootTrait;

class UfoJsonRpcBundle extends Bundle
{
    use DTOTransformerBundleBootTrait;

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $config = $container->getCompilerPassConfig();

        $passes = $config->getBeforeOptimizationPasses();
        $beforeRemoving = array_values(array_filter(
            $passes,
            static fn ($p) => !($p instanceof RegisterControllerArgumentLocatorsPass)
        ));

        $config->setBeforeOptimizationPasses($beforeRemoving);

        $container
            ->addCompilerPass(new RpcRegisterControllerArgumentLocatorsPass())
            ->addCompilerPass(new RpcServiceMapPass())
        ;
    }

    public function boot(): void
    {
        $this->bootDTOTransformers();
        parent::boot();
    }

    protected function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}
