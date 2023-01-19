<?php

namespace Ufo\JsonRpcBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Ufo\JsonRpcBundle\DependencyInjection\Compiler\ApiProceduresPass;

class UfoJsonRpcBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new ApiProceduresPass());
    }
}
