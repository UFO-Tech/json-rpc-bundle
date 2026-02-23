<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers;

use phpDocumentor\Reflection\DocBlock;
use ReflectionMethod;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\DtoReflector;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RPC\DTO;

use function class_exists;
use function is_array;

class ReturnFiller extends AbstractServiceFiller
{
    public function __construct(
        protected ChainParamConvertor $convertor,
    ) {}

    public function fill(ReflectionMethod $method, Service $service, DocBlock $methodDoc): void
    {
        $returnReflection = $method->getReturnType();
        $returns = $this->getTypes($returnReflection);
        $service->setReturn($returns, $this->getReturnType($methodDoc), $this->getReturnDescription($methodDoc));

        $dto = match(true) {
            class_exists($service->getReturn()['classFQCN'] ?? '') => new DTO($service->getReturn()['classFQCN']),
            class_exists($service->getReturn()['items']['classFQCN'] ?? '') => new DTO($service->getReturn()['items']['classFQCN'], true),
            default => null
        };

        if ($dto && !$service->getResponseInfo()) {
            new DtoReflector($dto, $this->convertor);
            $service->setResponseInfo($dto);
        }
    }

    protected function getReturnDescription(DocBlock $docBlock): string
    {
        $desc = '';
        /**
         * @var DocBlock\Tags\Return_ $return
         */
        foreach ($docBlock->getTagsByName('return') as $return) {
            $desc = $return->getDescription();
        }
        return $desc;

    }

    protected function getReturnType(DocBlock $docBlock): ?string
    {
        $tags = $docBlock->getTagsByName('return');
        if (empty($tags)) {
            return null;
        }
        return (string) $tags[0]->getType();
    }

}