<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers;

use phpDocumentor\Reflection\DocBlock;
use ReflectionMethod;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Ufo\JsonRpcBundle\ConfigService\RpcDocsConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RPC\AssertionsCollection;

class AssertionsFiller extends AbstractServiceFiller
{
    protected RpcDocsConfig $rpcDocsConfig;

    public function __construct(
        protected SerializerInterface $serializer,
        protected RpcMainConfig $rpcConfig,
    )
    {
        $this->rpcDocsConfig = $this->rpcConfig->docsConfig;
    }

    public function fill(ReflectionMethod $method, Service $service, DocBlock $methodDoc): void
    {

        if ($this->rpcConfig->docsConfig->needJsonSchema) {
            $this->buildAssertions($method, $service);
        }

        if ($this->rpcConfig->docsConfig->needJsonSchema) {
            $this->buildJsonSchema($service);
        }
    }

    protected function buildAssertions(ReflectionMethod $method, Service $service): void
    {
        $paramsReflection = $method->getParameters();
        $assertions = new AssertionsCollection();
        if (!empty($paramsReflection)) {
            foreach ($paramsReflection as $paramRef) {
                try {
                    $assertions->fillAssertion(
                        $service->getProcedureFQCN(),
                        $method,
                        $paramRef
                    );

                    $service->setUfoAssertions(
                        $paramRef->getName(),
                        $assertions->getAssertionsCollection()[$paramRef->getName()]?->constructorArgs ?? null
                    );
                } catch (Throwable) {}
            }
        }
        $service->setAssertions($assertions);
    }

    protected function buildJsonSchema(Service $service): void
    {
        try {
            $service->setSchema($this->serializer->normalize($service->getAssertions(), context: [
                'service' => $service,
            ]));
        } catch (Throwable $e) {}
    }

}