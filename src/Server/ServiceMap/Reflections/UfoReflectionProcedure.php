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
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers\ChainServiceFiller;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcObject\RPC\AssertionsCollection;
use Ufo\RpcObject\RPC\Cache;
use Ufo\RpcObject\RPC\IgnoreApi;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RPC\Response;
use Ufo\RpcObject\RPC\ResultAsDTO;

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
        protected RpcDocsConfig $rpcDocsConfig,
        protected ChainServiceFiller $chainServiceFiller,
    ) {
        $this->reflection = new ReflectionClass(get_class($procedure));
        $this->provideNameAndNamespace();
        foreach ($this->reflection->getMethods() as $method) {
            if (str_starts_with($method->getName(), '__') || count($method->getAttributes(IgnoreApi::class)) > 0) continue;

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
        } catch (Throwable) {
        } finally {
            $this->name = $procedureClassName;
            $this->namespace = $this->reflection->getNamespaceName();
        }
    }


    /**
     * @param ReflectionMethod $method
     * @return Service
     * @throws RpcInternalException
     */
    protected function buildSignature(ReflectionMethod $method): Service
    {
        if (!$docBlock = $method->getDocComment()) {
            $docBlock = static::EMPTY_DOC;
        }
        $this->methodDoc = DocBlockFactory::createInstance()->create($docBlock);
        $className = (empty($this->name)) ? '' : $this->name . $this->concat;
        $service = new Service($className.$method->getName(), $this->procedure::class, $this->concat);

        $this->chainServiceFiller->fill($method, $service, $this->methodDoc);
        return $service;
    }

    /**
     * @return Service[]
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

}
