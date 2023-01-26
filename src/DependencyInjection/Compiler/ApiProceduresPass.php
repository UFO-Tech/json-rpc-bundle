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
use Ufo\JsonRpcBundle\Server\RpcServerFacade;

/**
 * @deprecated will be removed or radically changed in version 5
 */
class ApiProceduresPass implements CompilerPassInterface
{

    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has(RpcServerFacade::class)) {
            return;
        }

        $definition = $container->findDefinition(RpcServerFacade::class);

        $taggedServices = $container->findTaggedServiceIds('ufo.rpc.service');

        foreach ($this->sortByPriority($taggedServices) as $id) {
//            $definition->addMethodCall('addProcedure', [new Reference($id)]);
        }
    }

    protected function sortByPriority($taggedServices)
    {
        $arrayMap = [];
        foreach ($taggedServices as $key => $service) {
            $priority = 100;
            if (isset($service[0]['priority'])) {
                $priority = $service[0]['priority'] * 100;
            }
            $arrayMapSetter = function ($key, $value) use (&$arrayMap, &$arrayMapSetter) {
                if (isset($arrayMap[$key])) {
                    $arrayMapSetter($key + 1, $value);
                    return;
                }
                $arrayMap[$key] = $value;
            };
            $arrayMapSetter($priority, $key);
        }
        krsort($arrayMap);
        return $arrayMap;
    }
}