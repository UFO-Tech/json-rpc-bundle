<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers;

use phpDocumentor\Reflection\DocBlock;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Throwable;
use Ufo\JsonRpcBundle\ConfigService\RpcDocsConfig;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RPC\AssertionsCollection;

use Ufo\RpcObject\RPC\Param;
use Ufo\RpcObject\Transformer\Transformer;

use function enum_exists;
use function get_object_vars;
use function in_array;
use function is_array;
use function is_string;

#[AutoconfigureTag(IServiceFiller::TAG, ['priority' => 99])]
class AssertionsFiller extends AbstractServiceFiller
{
    protected RpcDocsConfig $rpcDocsConfig;

    public function __construct(
        protected RpcMainConfig $rpcConfig,
        protected SerializerInterface $serializer
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

                    $assertion = $assertions->getAssertionsCollection()[$paramRef->getName()] ?? null;
                    $service->setUfoAssertions(
                        $paramRef->getName(),
                        $assertion && !$this->hasServerCallback($assertion->assertions) ? $assertion->constructorArgs : null
                    );
                } catch (Throwable $e) {}
            }
        }
        $service->setAssertions($assertions);
    }

    /** Choice з callback не на enum посилається на серверний клас, якого в SDK немає. */
    protected function hasServerCallback(mixed $constraint): bool
    {
        if (is_array($constraint)) {
            foreach ($constraint as $item) {
                if ($this->hasServerCallback($item)) {
                    return true;
                }
            }
            return false;
        }
        if (!$constraint instanceof Constraint) {
            return false;
        }
        if ($constraint instanceof Choice && $constraint->callback !== null) {
            return !(is_array($constraint->callback) && is_string($constraint->callback[0] ?? null) && enum_exists($constraint->callback[0]));
        }

        return $this->hasServerCallback(get_object_vars($constraint));
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
