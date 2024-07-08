<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections;

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\ConfigService\RpcDocsConfig;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RPC\Assertions;
use Ufo\RpcObject\RPC\AssertionsCollection;
use Ufo\RpcObject\RPC\Cache;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RPC\Response;
use Ufo\RpcObject\Transformer\AttributeHelper;

use function count;
use function current;

/**
 * Class/Object reflection
 */
class UfoReflectionProcedure
{
    const string EMPTY_DOC = '/**'.PHP_EOL.' *'.PHP_EOL.' */';

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

    protected bool $async = false;

    protected AssertionsCollection $assertions;

    public function __construct(
        protected IRpcService $procedure,
        protected SerializerInterface $serializer,
        protected RpcDocsConfig $rpcDocsConfig
    ) {
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

    protected function provideNameAndNamespace(): void
    {
        $procedureClassName = $this->reflection->getShortName();
        try {
            $infoAttribute = $this->reflection->getAttributes(Info::class)[0];
            /**
             * @var Info $info
             */
            $info = $infoAttribute->newInstance();
            $procedureClassName = $info->alias ?? $procedureClassName;
            $this->concat = $info->concat;
            $this->async = $info->async;
        } catch (Throwable) {
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
        $returnDesc = $this->getReturnDescription($this->methodDoc);
        if (is_array($returns)) {
            foreach ($returns as $type) {
                $service->addReturn($this->typeFrom($type), $returnDesc);
            }
        } else {
            $service->addReturn($this->typeFrom($returns), $returnDesc);
        }
        $cache = $method->getAttributes(Cache::class);
        if (count($cache) > 0) {
            $service->setCacheInfo(current($cache)->newInstance());
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

    protected function typeFrom(ReflectionNamedType|string $type): string
    {
        return ($type instanceof ReflectionNamedType) ? $type->getName() : $type;
    }

    protected function getTypes(?ReflectionType $reflection): array|string
    {
        $return = 'any';
        $returns = [];
        if ($reflection instanceof ReflectionNamedType) {
            $return = $reflection->getName();
            if ($reflection->allowsNull()) {
                $returns[] = $return;
                $returns[] = 'null';
            }
        } elseif ($reflection instanceof ReflectionUnionType) {
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
                    'type'       => $this->getTypes($paramRef->getType()),
                    'additional' => [
                        'name'        => $paramRef->getName(),
                        'description' => $this->getParamDescription($docBlock, $paramRef->getName()),
                        'optional'    => false,
                        'schema'      => [],
                    ],
                ];
                try {
                    $params[$i]['additional']['default'] = $paramRef->getDefaultValue();
                    $params[$i]['additional']['optional'] = true;
                } catch (ReflectionException) {
                }
                try {
                    
                    $this->assertions->fillAssertion(
                        $service->getProcedure()::class,
                        $method,
                        $paramRef
                    );
                } catch (Throwable) {
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
        $className = (empty($this->name)) ? '' : $this->name.$this->concat;
        $service = new Service($className.$method->getName(), $this->procedure, $this->concat);
        $this->buildParams($method, $service);
        $this->buildReturns($method, $service);
        $this->findResponseInfo($method, $service);
        $this->buildDescription($method, $service);
        $this->buildThrows($method, $service);
        if ($this->rpcDocsConfig->needJsonSchema) {
            $this->buildJsonSchema($method, $service);
        }
        if ($this->rpcDocsConfig->needSymfonyAsserts) {
            $this->buildAssets($method, $service);
        }

        return $service;
    }

    protected function buildDescription(ReflectionMethod $method, Service $service): void
    {
        $service->setDescription($this->methodDoc->getSummary());
    }

    protected function buildThrows(ReflectionMethod $method, Service $service): void
    {
        //        foreach ($this->methodDoc->getTagsByName('throws') as $throw) {
        //            $service->addThrow((string)$throw);
        //        }
    }

    protected function buildJsonSchema(ReflectionMethod $method, Service $service): void
    {
        try {
            $service->setSchema($this->serializer->normalize($this->assertions, context: [
                'service' => $service,
            ]));
        } catch (Throwable $e) {
            $a = 1;
        }
    }

    protected function buildAssets(ReflectionMethod $method, Service $service): void
    {
        $service->setAssertions($this->assertions);
    }

    /**
     * @return Service[]
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

}
