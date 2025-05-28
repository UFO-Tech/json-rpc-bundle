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

use function in_array;

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

//        if ($this->rpcConfig->docsConfig->needSymfonyAsserts) {
            $this->buildAssertions($method, $service);
//        }

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
                } catch (Throwable $e) {}
            }
        }
        $service->setAssertions($assertions);
    }

    protected function buildJsonSchema(Service $service): void
    {
        try {
            $data = $this->serializer->normalize(
                $service->getAssertions(),
                context: [
                    'service' => $service,
                ]
            );
            $service->setSchema($data);
        } catch (Throwable $e) {}
    }

}