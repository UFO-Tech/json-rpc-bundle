<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers;

use phpDocumentor\Reflection\DocBlock;
use ReflectionException;
use ReflectionMethod;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;

class ParamFiller extends AbstractServiceFiller
{

    public function fill(ReflectionMethod $method, Service $service, DocBlock $methodDoc): void
    {
        $params = [];
        $paramsReflection = $method->getParameters();
        if (!empty($paramsReflection)) {
            foreach ($paramsReflection as $i => $paramRef) {
                $params[$i] = [
                    'type'       => $this->getTypes($paramRef->getType()),
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
                } catch (ReflectionException) {
                }
            }
        }
        foreach ($params as $param) {
            $service->addParam($param['type'], $param['additional']);
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