<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers;

use ReflectionMethod;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use phpDocumentor\Reflection\DocBlock;

class ChainServiceFiller implements IServiceFiller
{

    public function __construct(
        #[AutowireIterator(IServiceFiller::TAG)]
        protected iterable $fillers = []
    ) {}

    public function fill(ReflectionMethod $method, Service $service, DocBlock $methodDoc): void {
        foreach ($this->fillers as $filler) {
            $filler->fill($method, $service, $methodDoc);
        }
    }
}