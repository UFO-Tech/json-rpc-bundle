<?php
/**
 * Created by PhpStorm.
 * User: ashterix
 * Date: 26.09.16
 * Time: 20:08
 */

namespace Ufo\JsonRpcBundle\DependencyInjection\Compiler;


use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ApiProceduresPass implements CompilerPassInterface
{

    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('ufo_api_server.zend_json_rpc_server_facade')) {
            return;
        }

        $definition = $container->findDefinition('ufo_api_server.zend_json_rpc_server_facade');

        $taggedServices = $container->findTaggedServiceIds('rpc.service');

        foreach ($taggedServices as $id => $tags) {
            $definition->addMethodCall('addProcedure', [new Reference($id)]);
        }
    }
}