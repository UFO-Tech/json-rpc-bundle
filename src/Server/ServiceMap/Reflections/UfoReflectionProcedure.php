<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections;


use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RPC\Assertions;
use Ufo\RpcObject\RPC\AssertionsCollection;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RPC\Response;

/**
 * Class/Object reflection
 */
class UfoReflectionProcedure
{
    const EMPTY_DOC = '/**' . PHP_EOL . ' *' . PHP_EOL . ' */';
    /**
     * @var ReflectionMethod[]
     */
    protected array $methods = [];

    protected string $namespace;

    /**
     * ReflectionClass object
     *
     * @var ReflectionClass
     */
    protected ReflectionClass $reflection;

    protected string $name;
    
    protected DocBlock $methodDoc;

    protected string $concat = Info::DEFAULT_CONCAT;

    protected AssertionsCollection $assertions;

    /**
     * @param IRpcService $procedure
     */
    public function __construct(
        protected IRpcService $procedure,
        protected SerializerInterface $serializer,
    )
    {
        $this->reflection = new ReflectionClass(get_class($procedure));

        $this->provideNameAndNamespace();
        
        foreach ($this->reflection->getMethods() as $method) {
            if (str_starts_with($method->getName(), '__')) {
                continue;
            }

            if ($method->isPublic()) {
                $this->methods[] = $this->buildSignature($method);
            }
        }
    }

    protected function provideNameAndNamespace()
    {
        $procedureClassName = $this->reflection->getShortName();
        try {
            $infoAttribut = $this->reflection->getAttributes(Info::class)[0];
            /**
             * @var Info $info
             */
            $info = $infoAttribut->newInstance();
            $procedureClassName = $info->getAlias() ?? $procedureClassName;
            $this->concat = $info->getConcat();

        } catch (\Throwable) {
        } finally {
            $this->name = $procedureClassName;
            $this->namespace = $this->reflection->getNamespaceName();
        }
    }

    protected function buildReturns(ReflectionMethod $method, Service $service): void
    {
        $returnReflection = $method->getReturnType();
        $returns = [];
        if (is_null($returnReflection) && is_string($method->getDocComment())) {
            foreach ($this->methodDoc->getTagsByName('return') as $return) {
                $returns[] = (string)$return->getType();
            }
        } else {
            $returns = $this->getTypes($returnReflection);
        }

        if (is_array($returns)) {
            foreach ($returns as $type) {
                $service->addReturn($this->typeFrom($type));
            }
        } else {
            $service->addReturn($this->typeFrom($returns));
        }
    }

    protected function findResponseInfo(ReflectionMethod $method, Service $service): void
    {
        $responseInfo = new Response();
        if ($method->getAttributes(Response::class)) {
            $responseInfo = $method->getAttributes(Response::class)[0]->newInstance();
        }
        $service->setResponseInfo($responseInfo);
    }

    protected function typeFrom(\ReflectionNamedType|string $type): string
    {
        return ($type instanceof \ReflectionNamedType) ? $type->getName() : $type;
    }

    protected function getTypes(?\ReflectionType $reflection): array|string
    {
        $return = 'any';
        $returns = [];
        if ($reflection instanceof \ReflectionNamedType) {
            $return = $reflection->getName();
            if ($reflection->allowsNull()) {
                $returns[] = $return;
                $returns[] = 'null';
            }
        } elseif ($reflection instanceof \ReflectionUnionType) {
            foreach ($reflection->getTypes() as $type) {
                $returns[] = $this->getTypes($type);
            }
        }
        return !empty($returns) ? $returns : $return;
    }

    protected function buildParams(ReflectionMethod $method, Service $service): void
    {
        $params = [];
        $paramsReflection = $method->getParameters();
        $this->assertions = new AssertionsCollection();
        if (!empty($paramsReflection)) {
            $docBlock = $this->methodDoc;
            foreach ($paramsReflection as $i => $paramRef) {
                $params[$i] = [
                    'type' => $this->getTypes($paramRef->getType()),
                    'additional' => [
                        'name' => $paramRef->getName(),
                        'description' => $this->getParamDescription($docBlock, $paramRef->getName()),
                        'optional' => false,
                        'schema'=>[]
                    ]
                ];
                try {
                    $params[$i]['additional']['default'] = $paramRef->getDefaultValue();
                    $params[$i]['additional']['optional'] = true;

                } catch (ReflectionException) {}
                try {
                    $this->assertions->addAssertions(
                        $paramRef->getName(),
                        $paramRef->getAttributes(Assertions::class)[0]->newInstance()
                    );
                } catch (\Throwable) {}
            }
        }
        foreach ($params as $param) {
            $service->addParam($param['type'], $param['additional']);
        }
    }

    protected function getParamDescription(DocBlock $docBlock, string $paramName)
    {
        /**
         * @var DocBlock\Tags\Param $param
         */
        foreach ($docBlock->getTagsByName('param') as $param) {
            if (!$param->getName() === $paramName) {
                continue;
            }
            return $param->getDescription()->getBodyTemplate();
        }
    }
    
    /**
     * @param ReflectionMethod $method
     * @return Service
     */
    protected function buildSignature(ReflectionMethod $method): Service
    {
        if (!$docBlock = $method->getDocComment()) {
            $docBlock = static::EMPTY_DOC;
        }

        $this->methodDoc = DocBlockFactory::createInstance()->create($docBlock);

        $className = (empty($this->name)) ? '' : $this->name . $this->concat;
        $service = new Service($className . $method->getName(), $this->procedure);
        $this->buildParams($method, $service);
        $this->buildReturns($method, $service);
        $this->findResponseInfo($method, $service);
        $this->buildDescription($method, $service);
        $this->buildJsonSchema($method, $service);
        return $service;
    }

    protected function buildDescription(ReflectionMethod $method, Service $service): void
    {
        $service->setDescription($this->methodDoc->getSummary());
    }

    protected function buildJsonSchema(ReflectionMethod $method, Service $service): void
    {
        try {
            $service->setSchema(
                $this->serializer->normalize(
                    $this->assertions, 
                    context: [
                        'service' => $service
                    ]
                )
            );
        } catch (\Throwable $e){$a=1;}
    }

    /**
     * @return Service[]
     */
    public function getMethods(): array
    {
        return $this->methods;
    }
}
