<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers;

use phpDocumentor\Reflection\DocBlock;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use TypeError;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\DtoReflector;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcObject\Helpers\TypeHintResolver;
use Ufo\RpcObject\RPC\DTO;

use function array_map;
use function class_exists;

#[AutoconfigureTag(IServiceFiller::TAG, ['priority' => 100])]
class ParamFiller extends AbstractServiceFiller
{

    public function fill(ReflectionMethod $method, Service $service, DocBlock $methodDoc): void
    {
        $params = [];
        $paramsReflection = $method->getParameters();
        if (!empty($paramsReflection)) {
            foreach ($paramsReflection as $i => $paramRef) {

                $type = $this->getType($paramRef, $service);
                $params[$i] = [
                    'type' => $type,
                    'additional' => [
                        'name'        => $paramRef->getName(),
                        'description' => $this->getParamDescription($methodDoc, $paramRef->getName()),
                        'optional'    => false,
                        'schema'      => [],
                    ],
                ];
                try {
                    $params[$i]['additional']['default'] = $paramRef->getDefaultValue();
                    $params[$i]['additional']['optional'] = true;
                } catch (ReflectionException) {}
            }
        }
        foreach ($params as $param) {
            $service->addParam($param['type'], $param['additional']);
        }
    }

    /**
     * @throws RpcInternalException
     */
    protected function getType(ReflectionParameter $paramRef, Service $service): string|array
    {
        $type = $this->getTypes($paramRef->getType());
        $this->checkDTO($type, $paramRef->getName(), $service);
        return $type;
    }

    /**
     * @throws RpcInternalException
     */
    protected function checkDTO(string|array $type, string $paramName, Service $service): void
    {
        if (is_array($type)) {
            array_map(fn(string $type) => $this->checkDTO($type, $paramName, $service), $type);
            return;
        }
        $nType = TypeHintResolver::normalize($type);
        if ($nType === TypeHintResolver::OBJECT->value && class_exists($type)) {
            $service->addParamsDto($paramName, new DtoReflector(new DTO($type)));
        }
    }


    protected function getParamDescription(DocBlock $docBlock, string $paramName): string
    {
        $desc = '';
        /**
         * @var DocBlock\Tags\Param $param
         */
        foreach ($docBlock->getTagsByName('param') as $param) {
            if (!($param->getVariableName() === $paramName)) {
                continue;
            }
            if ($param->getDescription()) {
                $desc = $param->getDescription()->getBodyTemplate();
            }
            break;
        }

        return $desc;
    }

}