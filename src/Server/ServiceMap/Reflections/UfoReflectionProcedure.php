<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections;


use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RpcInfo;

/**
 * Class/Object reflection
 */
class UfoReflectionProcedure
{
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

    /**
     * @param IRpcService $procedure
     */
    public function __construct(protected IRpcService $procedure)
    {
        $reflection = new ReflectionClass(get_class($procedure));
        $this->reflection = $reflection;
        $this->name = $reflection->getShortName();
        $this->namespace = $reflection->getNamespaceName();

        foreach ($reflection->getMethods() as $method) {
            if (str_starts_with($method->getName(), '__')) {
                continue;
            }

            if ($method->isPublic()) {
                $this->methods[] = $this->buildSignature($method);
            }
        }
    }

    protected function buildReturns(ReflectionMethod $method, Service $service): void
    {
        $returnReflection = $method->getReturnType();
        $returns = [];

        if (is_null($returnReflection) && is_string($method->getDocComment())) {
            $parser = DocBlockFactory::createInstance()->create($method->getDocComment());
            foreach ($parser->getTagsByName('return') as $return) {
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

    protected function findRpcInfo(ReflectionMethod $method, Service $service): void
    {
        $rpcInfo = new RpcInfo();
        if ($method->getAttributes(RpcInfo::class)) {
            $rpcInfo = $method->getAttributes(RpcInfo::class)[0]->newInstance();
        }
        $service->setRpcInfo($rpcInfo);
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
        if (!empty($paramsReflection)) {
            foreach ($paramsReflection as $i => $paramRef) {
                $params[$i] = [
                    'type' => $this->getTypes($paramRef->getType()),
                    'additional' => [
                        'name' => $paramRef->getName(),
                        'optional' => false,
                    ]
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
     * @param ReflectionMethod $method
     * @return Service
     */
    protected function buildSignature(ReflectionMethod $method): Service
    {
        $service = new Service($this->name . '.' . $method->getName(), $this->procedure);
        $this->buildParams($method, $service);
        $this->buildReturns($method, $service);
        $this->findRpcInfo($method, $service);
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
