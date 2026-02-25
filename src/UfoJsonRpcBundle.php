<?php

namespace Ufo\JsonRpcBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\RegisterControllerArgumentLocatorsPass;
use Ufo\JsonRpcBundle\DependencyInjection\CompilerPass\RpcRegisterArgumentLocatorsPass;
use Ufo\JsonRpcBundle\DependencyInjection\CompilerPass\RpcRegisterControllerArgumentLocatorsPass;
use Ufo\JsonRpcBundle\DependencyInjection\CompilerPass\RpcServiceMapPass;

class UfoJsonRpcBundle extends Bundle
{
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
            ->addCompilerPass(new RpcRegisterArgumentLocatorsPass())
        ;
    }
}
